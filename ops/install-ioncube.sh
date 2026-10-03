#!/usr/bin/env bash
set -Eeuo pipefail

# Host-managed binaries stay outside immutable source releases. Apache uses
# glibc; the official WP-CLI/scheduler image uses musl, so they need two loaders.
runtime_dir="${CHIDEMOON_RUNTIME_DIR:-/opt/chidemoon/runtime}"
php_minor="${CHIDEMOON_IONCUBE_PHP_MINOR:-8.2}"
loader_version="${CHIDEMOON_IONCUBE_VERSION:-15.5.1}"
apache_image="${CHIDEMOON_IONCUBE_APACHE_IMAGE:-wordpress:7.1-php8.2-apache}"
cli_image="${CHIDEMOON_IONCUBE_CLI_IMAGE:-wordpress:cli-2.10.0-php8.2}"

fail() { printf 'ionCube installation failed: %s\n' "$1" >&2; exit 1; }
for command in docker curl tar sha256sum file flock mktemp cmp chown; do
	command -v "$command" >/dev/null 2>&1 || fail "Required command is unavailable: $command"
done
[[ "$(uname -s)" = Linux && "$(uname -m)" = x86_64 ]] || fail 'This installer supports the verified Linux x86_64 host only.'
[[ "$(id -u)" = 0 ]] || fail 'Run as root so the persistent loader remains owned by root.'
[[ "$runtime_dir" = /* && "$php_minor" =~ ^[78]\.[0-9]+$ ]] || fail 'An absolute runtime directory and a PHP minor version are required.'
[[ "$loader_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail 'Expected ionCube version must be explicit.'
[[ "$runtime_dir" != *','* && "$runtime_dir" != *':'* ]] || fail 'Runtime directory cannot contain Docker mount or PHP scan separators.'
mkdir -p "$runtime_dir"
chmod 755 "$runtime_dir"
exec 9>"$runtime_dir/.ioncube-install.lock"
flock -n 9 || fail 'Another ionCube installation is running.'
[[ ! -e "$runtime_dir/ioncube" || -L "$runtime_dir/ioncube" ]] || fail 'Preserve the existing ioncube directory manually before switching to versioned runtime storage.'

scratch_dir="$(mktemp -d "$runtime_dir/.ioncube-stage.XXXXXX")"
cleanup() { [[ ! -d "$scratch_dir" ]] || rm -rf -- "$scratch_dir"; }
trap cleanup EXIT
mkdir -p "$scratch_dir/glibc/conf.d" "$scratch_dir/musl/conf.d" "$scratch_dir/downloads"
find "$scratch_dir" -type d -exec chmod 755 {} +

probe='printf("%s|%s|%d|%d|%d|%s", PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION, PHP_OS_FAMILY, PHP_ZTS, PHP_DEBUG, PHP_INT_SIZE * 8, php_uname("m"));'
for image in "$apache_image" "$cli_image"; do
	actual="$(docker run --rm --pull never --read-only --network none --entrypoint php "$image" -r "$probe")"
	[[ "$actual" = "$php_minor|Linux|0|0|64|x86_64" ]] || fail "Image PHP ABI does not match the selected loader: $image ($actual)."
done
docker run --rm --pull never --read-only --network none --entrypoint sh "$apache_image" -c 'ldd --version 2>&1 | grep -qi "glibc\|GNU libc"' || fail 'Apache image is not glibc.'
docker run --rm --pull never --read-only --network none --entrypoint sh "$cli_image" -c 'ldd --version 2>&1 | grep -qi musl' || fail 'WP-CLI image is not musl.'

install_loader() {
	local abi="$1" official_url="$2" supplied_archive="$3" supplied_sha="$4" image="$5"
	local archive="$scratch_dir/downloads/$abi.tar.gz" actual_sha loader="ioncube_loader_lin_${php_minor}.so"
	if [[ "$abi" = musl ]]; then loader="ioncube_loader_lin-musl_${php_minor}.so"; fi
	if [[ -n "$supplied_archive" ]]; then
		[[ -f "$supplied_archive" && "$supplied_sha" =~ ^[a-f0-9]{64}$ ]] || fail "A supplied official $abi archive requires its SHA-256."
		cp -- "$supplied_archive" "$archive"
	else
		curl --fail --location --proto '=https' --proto-redir '=https' --tlsv1.2 --connect-timeout 15 --max-time 300 --retry 2 --output "$archive" "$official_url"
	fi
	actual_sha="$(sha256sum "$archive" | awk '{print $1}')"
	[[ -z "$supplied_sha" || "$actual_sha" = "$supplied_sha" ]] || fail "The $abi archive checksum does not match."
	[[ "$(tar -tzf "$archive" | awk -v path="ioncube/$loader" '$0 == path { n++ } END { print n+0 }')" = 1 ]] || fail "Official $abi archive must contain exactly one selected PHP loader."
	tar -tvzf "$archive" "ioncube/$loader" | awk 'substr($1,1,1) != "-" { exit 1 }' || fail "Selected $abi loader is not a regular archive file."
	tar -xOzf "$archive" "ioncube/$loader" > "$scratch_dir/$abi/$loader"
	file "$scratch_dir/$abi/$loader" | grep -Eq 'ELF 64-bit.*x86-64' || fail "The $abi binary is not ELF x86_64."
	printf 'zend_extension=/opt/chidemoon-ioncube/%s/%s\n' "$abi" "$loader" > "$scratch_dir/$abi/conf.d/00-ioncube.ini"
	chmod 644 "$scratch_dir/$abi/$loader" "$scratch_dir/$abi/conf.d/00-ioncube.ini"
	# The scan order loads ionCube before OPcache and retains every stock extension.
	docker run --rm --pull never --read-only --network none --mount "type=bind,src=$scratch_dir,dst=/opt/chidemoon-ioncube,readonly" \
		--env "PHP_INI_SCAN_DIR=/opt/chidemoon-ioncube/$abi/conf.d:/usr/local/etc/php/conf.d" --entrypoint php "$image" -r \
		'$parts = explode(".", $argv[1]); $expected = (int) $parts[0] * 10000 + (int) $parts[1] * 100 + (int) $parts[2]; if (!extension_loaded("ionCube Loader") || !function_exists("ioncube_loader_iversion") || ioncube_loader_iversion() !== $expected || phpversion("ionCube Loader") !== $argv[1] || !extension_loaded("curl") || !extension_loaded("mysqli") || !extension_loaded("intl") || !extension_loaded("mbstring")) { fwrite(STDERR,"Loader version or original PHP extensions missing.\n"); exit(1); } printf("Verified PHP %s, ionCube %s and stock extensions.\n",PHP_VERSION,phpversion("ionCube Loader"));' -- "$loader_version" \
		|| fail "The official $abi loader failed its PHP compatibility check."
	printf '%s %s %s\n' "$abi" "$actual_sha" "$official_url" >> "$scratch_dir/official-archives.sha256"
}

install_loader glibc 'https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz' "${CHIDEMOON_IONCUBE_GLIBC_ARCHIVE:-}" "${CHIDEMOON_IONCUBE_GLIBC_SHA256:-}" "$apache_image"
install_loader musl 'https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin-musl_x86-64.tar.gz' "${CHIDEMOON_IONCUBE_MUSL_ARCHIVE:-}" "${CHIDEMOON_IONCUBE_MUSL_SHA256:-}" "$cli_image"
rm -rf -- "$scratch_dir/downloads"
find "$scratch_dir" -type d -exec chmod 755 {} +
find "$scratch_dir" -type f -exec chmod 644 {} +
chown -R root:root "$scratch_dir"
(
	cd "$scratch_dir"
	sha256sum glibc/*.so glibc/conf.d/*.ini musl/*.so musl/conf.d/*.ini official-archives.sha256 > installed-files.sha256
)
identity="$(sha256sum "$scratch_dir/installed-files.sha256" | awk '{print substr($1,1,16)}')"
version_dir="$runtime_dir/ioncube-${php_minor}-${identity}"
if [[ -e "$version_dir" ]]; then
	(cd "$version_dir" && sha256sum -c installed-files.sha256) || fail 'Existing immutable ionCube runtime is damaged.'
	cmp "$version_dir/installed-files.sha256" "$scratch_dir/installed-files.sha256" || fail 'Runtime identity collision.'
else
	mv "$scratch_dir" "$version_dir"
fi
previous_runtime=''
if [[ -L "$runtime_dir/ioncube" ]]; then previous_runtime="$(readlink -f "$runtime_dir/ioncube")"; fi
if [[ "$previous_runtime" = "$version_dir" ]]; then
	printf 'Verified ionCube runtime is already active: %s\n' "$version_dir"
	exit 0
fi
printf '%s\n' "$previous_runtime" > "$runtime_dir/ioncube-previous.txt"
chmod 644 "$runtime_dir/ioncube-previous.txt"
printf '%s\n%s\n' "$previous_runtime" "$version_dir" > "$runtime_dir/ioncube-history-$(date -u +%Y%m%dT%H%M%SZ)-$$.txt"
next_link="$runtime_dir/.ioncube-next-$$"
ln -s "$(basename "$version_dir")" "$next_link"
mv -Tf "$next_link" "$runtime_dir/ioncube"
printf 'Installed verified persistent ionCube runtime: %s\nPrevious runtime: %s\nRecreate WordPress with the reviewed Compose release to enable it.\n' "$version_dir" "${previous_runtime:-none}"
