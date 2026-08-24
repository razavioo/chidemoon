#!/usr/bin/env bash

# Shared archive helpers deliberately parse only the narrow image syntax emitted
# by Compose. Keeping the parser restrictive prevents a tampered artifact from
# turning an image reference into shell input during an offline deployment.

chidemoon_release_root() {
	local bundle_path="$1"
	local first_entry

	first_entry="$(tar -tzf "$bundle_path" | sed -n '1p')" || return 1
	first_entry="${first_entry%%/*}"
	[[ "$first_entry" =~ ^chidemoon-release-[A-Za-z0-9._-]+$ ]] || return 1
	printf '%s\n' "$first_entry"
}

chidemoon_release_compose_images() {
	local bundle_path="$1"
	local release_root

	release_root="$(chidemoon_release_root "$bundle_path")" || return 1
	tar -xOf "$bundle_path" "$release_root/compose.yml" |
		awk '
			/^[[:space:]]*image:[[:space:]]*/ {
				image = $0
				sub(/^[[:space:]]*image:[[:space:]]*/, "", image)
				sub(/[[:space:]]+#.*/, "", image)
				gsub(/^[[:space:]]+|[[:space:]]+$/, "", image)
				if (image !~ /^[A-Za-z0-9][A-Za-z0-9._/:@-]*$/) {
					exit 64
				}
				print image
			}
		' |
		LC_ALL=C sort -u
}

chidemoon_read_image_lock() {
	local lock_path="$1"
	local expected_bundle_sha="$2"
	local expected_release_root="$3"
	local -a lines
	local line
	local reference
	local image_id
	local bundle_sha
	local release_root
	local index
	declare -A seen_references=()

	mapfile -t lines < "$lock_path" || return 1
	(( ${#lines[@]} >= 4 )) || return 1
	[[ "${lines[0]}" == 'format=1' ]] || return 1

	bundle_sha="${lines[1]#release_bundle_sha256=}"
	release_root="${lines[2]#release_root=}"
	[[ "${lines[1]}" == "release_bundle_sha256=$bundle_sha" ]] || return 1
	[[ "$bundle_sha" =~ ^[a-f0-9]{64}$ ]] || return 1
	[[ "$bundle_sha" == "$expected_bundle_sha" ]] || return 1
	[[ "${lines[2]}" == "release_root=$release_root" ]] || return 1
	[[ "$release_root" == "$expected_release_root" ]] || return 1

	for (( index = 3; index < ${#lines[@]}; index++ )); do
		line="${lines[$index]}"
		[[ "$line" =~ ^image=([A-Za-z0-9][A-Za-z0-9._/:@-]*)\ id=(sha256:[a-f0-9]{64})$ ]] || return 1
		reference="${BASH_REMATCH[1]}"
		image_id="${BASH_REMATCH[2]}"
		[[ -z "${seen_references[$reference]:-}" ]] || return 1
		seen_references[$reference]=1
		printf '%s\t%s\n' "$reference" "$image_id"
	done
}
