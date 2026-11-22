param(
    [Parameter(Mandatory)][ValidateSet('Pull', 'Push')][string]$Direction,
    [Parameter(Mandatory)][ValidateRange(1, 2147483647)][int]$LookId,
    [ValidateRange(0, 2147483647)][int]$DestinationId = 0
)

$ErrorActionPreference = 'Stop'
$container = 'chidemoon-wordpress-1'
$transferPath = '/tmp/chidemoon-transfer-shop-look.php'
$bundlePath = '/tmp/chidemoon-transfer-' + [Guid]::NewGuid().ToString('N') + '.json'
$scriptPath = Join-Path $PSScriptRoot 'transfer-shop-look.php'
Get-Content -Raw -Encoding utf8 $scriptPath | ssh -o BatchMode=yes chidemoon-vps "docker exec -i $container sh -c 'cat > $transferPath'"
if ($LASTEXITCODE -ne 0) { throw 'Could not prepare the tool on the server' }
docker cp $scriptPath "${container}:$transferPath"
if ($LASTEXITCODE -ne 0) { throw 'Could not prepare the tool locally' }
$bundle = if ($Direction -eq 'Pull') {
    ssh -n -o BatchMode=yes chidemoon-vps "docker exec $container php $transferPath $LookId"
} else {
    docker exec $container php $transferPath $LookId
}
if ($LASTEXITCODE -ne 0 -or !$bundle) { throw 'Could not export the look' }
try {
    if ($Direction -eq 'Pull') {
        $bundle | docker exec -i $container sh -c "cat > $bundlePath"
        if ($LASTEXITCODE -ne 0) { throw 'Could not stage the exported look locally' }
        docker exec $container php $transferPath $bundlePath $DestinationId
    } else {
        $bundle | ssh -o BatchMode=yes chidemoon-vps "docker exec -i $container sh -c 'cat > $bundlePath'"
        if ($LASTEXITCODE -ne 0) { throw 'Could not stage the exported look on the server' }
        ssh -n -o BatchMode=yes chidemoon-vps "docker exec $container php $transferPath $bundlePath $DestinationId"
    }
    if ($LASTEXITCODE -ne 0) { throw 'Could not import the look' }
} finally {
    if ($Direction -eq 'Pull') {
        docker exec $container rm -f $bundlePath
    } else {
        ssh -n -o BatchMode=yes chidemoon-vps "docker exec $container rm -f $bundlePath"
    }
}
