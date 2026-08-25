[CmdletBinding()]
param(
    [string]$AdminUser = 'chidemoon-editor',
    [string]$AdminPassword = 'local-preview-only',
    [string]$AdminEmail = 'editor@example.test'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$composeFile = Join-Path $PSScriptRoot 'local-compose.yml'
$siteUrl = 'http://localhost:18088'
$retiredPlaceholder = '<!-- wp:shortcode --><!-- /wp:shortcode -->'

function Invoke-Compose {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)

    # Compose emits normal container lifecycle lines on stderr. Redirect those
    # lines so PowerShell cannot mistake a healthy zero-exit invocation for an
    # exception; the process exit code remains the authoritative failure signal.
    $previousErrorAction = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & docker compose -f $composeFile @Arguments 2>$null
        $composeExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
    if ($composeExitCode -ne 0) {
        throw "Docker Compose failed: $($Arguments -join ' ')"
    }
}

function Invoke-Wp {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)

    # The WordPress image entrypoint executes its first argument directly.
    # Pinning WP-CLI here keeps the preview reproducible across image variants.
    Invoke-Compose run --rm --no-TTY --user root --entrypoint wp wpcli @Arguments --allow-root
}

function Ensure-Page {
    param(
        [string]$Slug,
        [string]$Title,
        [string]$Content = ''
    )

    $pageId = Invoke-Wp post list "--post_type=page" "--name=$Slug" --field=ID --format=ids
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to look up the local page '$Slug'."
    }
    if (-not [string]::IsNullOrWhiteSpace(($pageId -join ''))) {
        return [int](($pageId -join '').Trim())
    }

    $createdId = Invoke-Wp post create --post_type=page --post_status=publish "--post_name=$Slug" "--post_title=$Title" "--post_content=$Content" --porcelain
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace(($createdId -join ''))) {
        throw "Unable to create the local page '$Slug'."
    }
    return [int](($createdId -join '').Trim())
}

& docker version --format '{{.Server.Version}}'
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Desktop must be running and accessible before the local preview can start.'
}

Invoke-Compose up --detach

for ($attempt = 1; $attempt -le 30; $attempt++) {
    & curl.exe --noproxy '*' --silent --show-error --head --max-time 3 "$siteUrl/wp-login.php" | Out-Null
    if ($LASTEXITCODE -eq 0) {
        break
    }
    if ($attempt -eq 30) {
        throw 'The local WordPress container did not become reachable within 90 seconds.'
    }
    Start-Sleep -Seconds 3
}

& docker compose -f $composeFile run --rm --no-TTY --user root --entrypoint wp wpcli core is-installed --allow-root
$coreInstalled = $LASTEXITCODE -eq 0
if (-not $coreInstalled) {
    Invoke-Wp core install "--url=$siteUrl" --title=Chidemoon --admin_user=$AdminUser "--admin_password=$AdminPassword" "--admin_email=$AdminEmail" --skip-email
}

# Keep existing local volumes navigable when the preview port changes.
Invoke-Wp option update home $siteUrl
Invoke-Wp option update siteurl $siteUrl

Invoke-Wp plugin activate kalahamoon
Invoke-Wp theme activate chidemoon-theme
Invoke-Wp option update show_on_front page
Invoke-Wp option update WPLANG fa_IR
Invoke-Wp option update timezone_string Asia/Tehran
Invoke-Wp option update default_comment_status closed
Invoke-Wp option update default_ping_status closed
Invoke-Wp option update kalahamoon_catalog_authority remote

$homeId = Ensure-Page -Slug 'home' -Title 'Chidemoon' -Content $retiredPlaceholder
Invoke-Wp option update page_on_front $homeId

# These pages are intentionally skeletal: the preview proves empty, prelaunch,
# and editor states without inventing products or editorial approval evidence.
Ensure-Page -Slug 'shop' -Title 'Products'
Ensure-Page -Slug 'compare' -Title 'Compare products'
Ensure-Page -Slug 'shop-the-look' -Title 'Shop the look' -Content $retiredPlaceholder
Ensure-Page -Slug 'guides' -Title 'Buying guides'
Ensure-Page -Slug 'magazine' -Title 'Magazine' -Content $retiredPlaceholder
Ensure-Page -Slug 'about' -Title 'About Chidemoon' -Content 'Local editorial preview.'
Ensure-Page -Slug 'faq' -Title 'Frequently asked questions'
Ensure-Page -Slug 'contact' -Title 'Contact' -Content '[kalahamoon_lead_form intent="contact"]'
Ensure-Page -Slug 'report-issue' -Title 'Report a problem' -Content '[kalahamoon_lead_form intent="issue"]'
Ensure-Page -Slug 'expert-request' -Title 'Request an expert' -Content '[kalahamoon_lead_form intent="consultation"]'

$shopTheLookId = Ensure-Page -Slug 'shop-the-look' -Title 'Shop the look'
$magazineId = Ensure-Page -Slug 'magazine' -Title 'Magazine'

# The three native discovery pages are source-owned fixtures. Resetting only
# those local pages lets every run pick up layout changes without disturbing
# editor-created posts, products, or form entries in the disposable preview.
Invoke-Wp post update $homeId "--post_content=$retiredPlaceholder"
Invoke-Wp post update $magazineId "--post_content=$retiredPlaceholder"
Invoke-Wp post update $shopTheLookId "--post_content=$retiredPlaceholder"

# Theme templates own the native discovery layouts, so local setup must not
# invoke the retired content-migration command or overwrite editor content.
Invoke-Wp rewrite structure '/%postname%/'
Invoke-Wp rewrite flush --hard

Write-Output "Chidemoon local preview is ready at $siteUrl"
Write-Output "Local editor login: $AdminUser"
