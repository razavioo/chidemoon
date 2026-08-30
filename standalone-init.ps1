[CmdletBinding()]
param(
    [string]$AdminUser,
    [string]$AdminPassword,
    [string]$AdminEmail,
    [switch]$AllowNetworkThemeDownload,
    [switch]$Seed
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repositoryRoot = $PSScriptRoot
$composeFile = Join-Path $repositoryRoot 'compose.yml'
$environmentFile = Join-Path $repositoryRoot '.env'

if (-not (Test-Path -LiteralPath $environmentFile)) {
    throw 'Copy .env.example to .env and replace its placeholder secrets before initializing Chidemoon.'
}

function Get-EnvironmentValue {
    param([string]$Name, [string]$Fallback = '')

    $line = Get-Content -LiteralPath $environmentFile | Where-Object {
        $_ -match "^$([regex]::Escape($Name))=(.*)$"
    } | Select-Object -Last 1

    if ($null -eq $line) {
        return $Fallback
    }

    return ($line -replace "^$([regex]::Escape($Name))=", '').Trim()
}

function Invoke-Compose {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)

    & docker compose --env-file $environmentFile -f $composeFile @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker Compose failed: $($Arguments -join ' ')"
    }
}

function Invoke-Wp {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)

    Invoke-Compose run --rm --no-deps wpcli @Arguments --allow-root
}

function Test-Wp {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)

    & docker compose --env-file $environmentFile -f $composeFile run --rm --no-deps wpcli @Arguments --allow-root
    return $LASTEXITCODE -eq 0
}

function Ensure-Page {
    param([string]$Slug, [string]$Title)

    $existingId = & docker compose --env-file $environmentFile -f $composeFile run --rm --no-deps wpcli post list "--post_type=page" "--name=$Slug" --field=ID --format=ids --allow-root
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to look up page '$Slug'."
    }
    if (-not [string]::IsNullOrWhiteSpace(($existingId -join '').Trim())) {
        return [int](($existingId -join '').Trim())
    }

    $createdId = & docker compose --env-file $environmentFile -f $composeFile run --rm --no-deps wpcli post create --post_type=page --post_status=publish "--post_name=$Slug" "--post_title=$Title" --porcelain --allow-root
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace(($createdId -join '').Trim())) {
        throw "Unable to create page '$Slug'."
    }

    return [int](($createdId -join '').Trim())
}

function Remove-Post-If-Present {
    param([int]$PostId)

    # A rerun may observe a default post that another bootstrap step has already
    # removed. Confirm it still exists before making deletion an initializer error.
    if (Test-Wp post get $PostId --field=ID) {
        Invoke-Wp post delete $PostId --force
    }
}

function Ensure-NavigationMenu {
    $menuProvisioning = @'
$menuName = 'Chidemoon primary';
$menu = wp_get_nav_menu_object( $menuName );
$menuId = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( $menuName );
if ( $menuId <= 0 || is_wp_error( $menuId ) ) {
    WP_CLI::error( 'Unable to create the primary navigation menu.' );
}

$existingItems = wp_get_nav_menu_items( $menuId );
$existingPageIds = array();
if ( is_array( $existingItems ) ) {
    foreach ( $existingItems as $item ) {
        if ( 'post_type' === $item->type && 'page' === $item->object ) {
            $existingPageIds[] = (int) $item->object_id;
        }
    }
}

foreach ( array( 'home', 'magazine', 'guides', 'comparisons', 'shop-the-look', 'shop' ) as $slug ) {
    $page = get_page_by_path( $slug );
    if ( $page instanceof WP_Post && ! in_array( (int) $page->ID, $existingPageIds, true ) ) {
        wp_update_nav_menu_item(
            $menuId,
            0,
            array(
                'menu-item-object-id' => $page->ID,
                'menu-item-object'    => 'page',
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            )
        );
    }
}

$locations = get_theme_mod( 'nav_menu_locations', array() );
if ( ! is_array( $locations ) ) {
    $locations = array();
}
foreach ( array( 'menu_1', 'menu_mobile', 'footer' ) as $location ) {
    $locations[ $location ] = $menuId;
}
set_theme_mod( 'nav_menu_locations', $locations );
'@

    Invoke-Wp eval $menuProvisioning
}

& docker version --format '{{.Server.Version}}'
if ($LASTEXITCODE -ne 0) {
    throw 'Docker must be running and accessible before Chidemoon can initialize.'
}

$siteUrl = Get-EnvironmentValue -Name 'CHIDEMOON_SITE_URL' -Fallback 'http://localhost:18098'
$adminUser = if ($AdminUser) { $AdminUser } else { Get-EnvironmentValue -Name 'CHIDEMOON_ADMIN_USER' -Fallback 'chidemoon-editor' }
$adminPassword = if ($AdminPassword) { $AdminPassword } else { Get-EnvironmentValue -Name 'CHIDEMOON_ADMIN_PASSWORD' }
$adminEmail = if ($AdminEmail) { $AdminEmail } else { Get-EnvironmentValue -Name 'CHIDEMOON_ADMIN_EMAIL' -Fallback 'editor@example.test' }

if ([string]::IsNullOrWhiteSpace($adminPassword) -or $adminPassword -match '^replace-with-') {
    throw 'Set a non-placeholder CHIDEMOON_ADMIN_PASSWORD in .env before initialization.'
}

Invoke-Compose up --detach database wordpress

for ($attempt = 1; $attempt -le 30; $attempt++) {
    if (Test-Wp core version) {
        break
    }
    if ($attempt -eq 30) {
        throw 'The WordPress filesystem did not become available within 90 seconds.'
    }
    Start-Sleep -Seconds 3
}

if (-not (Test-Wp core is-installed)) {
    Invoke-Wp core install "--url=$siteUrl" --title=Chidemoon "--admin_user=$adminUser" "--admin_password=$adminPassword" "--admin_email=$adminEmail" --skip-email
}

Invoke-Wp option update home $siteUrl
Invoke-Wp option update siteurl $siteUrl

if (-not (Test-Wp plugin is-installed woocommerce)) {
    $offlineWooCommerce = Join-Path $repositoryRoot 'vendor\woocommerce.zip'
    if (Test-Path -LiteralPath $offlineWooCommerce) {
        Invoke-Wp plugin install /packages/woocommerce.zip --activate
    } elseif ($AllowNetworkThemeDownload) {
        Invoke-Wp plugin install woocommerce --activate
    } else {
        throw 'WooCommerce is required. Place its reviewed ZIP at vendor\woocommerce.zip, or use -AllowNetworkThemeDownload for a disposable local preview.'
    }
} else {
    Invoke-Wp plugin activate woocommerce
}

if (-not (Test-Wp theme is-installed blocksy)) {
    $offlineTheme = Join-Path $repositoryRoot 'vendor\blocksy.zip'
    if (Test-Path -LiteralPath $offlineTheme) {
        Invoke-Wp theme install /packages/blocksy.zip
    } elseif ($AllowNetworkThemeDownload) {
        Invoke-Wp theme install blocksy
    } else {
        throw 'Blocksy is required. Place its reviewed ZIP at vendor\blocksy.zip, or use -AllowNetworkThemeDownload for a disposable local preview.'
    }
}

Invoke-Wp plugin activate chidemoon-core
Invoke-Wp plugin activate chidemoon-ai
Invoke-Wp theme activate chidemoon-blocksy-child

Invoke-Wp language core install fa_IR
Invoke-Wp language plugin install woocommerce fa_IR
Invoke-Wp option update WPLANG fa_IR
Invoke-Wp option update timezone_string Asia/Tehran
Invoke-Wp option update default_comment_status closed
Invoke-Wp option update default_ping_status closed
Invoke-Wp rewrite structure '/%postname%/'

$homePageId = Ensure-Page -Slug 'home' -Title 'Chidemoon'
$blogPageId = Ensure-Page -Slug 'magazine' -Title 'Magazine'
$null = Ensure-Page -Slug 'guides' -Title 'Buying guides'
$null = Ensure-Page -Slug 'comparisons' -Title 'Comparisons'
$null = Ensure-Page -Slug 'shop-the-look' -Title 'Shop the look'

Invoke-Wp option update show_on_front page
Invoke-Wp option update page_on_front $homePageId
Invoke-Wp option update page_for_posts $blogPageId
Ensure-NavigationMenu

$helloWorldId = & docker compose --env-file $environmentFile -f $composeFile run --rm --no-deps wpcli post list --post_type=post --name=hello-world --field=ID --format=ids --allow-root
if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace(($helloWorldId -join '').Trim())) {
    Remove-Post-If-Present -PostId ([int](($helloWorldId -join '').Trim()))
}

$samplePageId = & docker compose --env-file $environmentFile -f $composeFile run --rm --no-deps wpcli post list --post_type=page --name=sample-page --field=ID --format=ids --allow-root
if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace(($samplePageId -join '').Trim())) {
    Remove-Post-If-Present -PostId ([int](($samplePageId -join '').Trim()))
}

Invoke-Wp rewrite flush --hard

if ($Seed) {
    Invoke-Wp eval-file /tools/seed-editorial.php
}

Write-Output "Chidemoon is ready at $siteUrl"
Write-Output "Admin user: $adminUser"
