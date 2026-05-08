# ==============================================================================
#  Get-AnimeData.ps1 (Version Hoshimi Sync)
#  Utilise les droits Windows pour créer metadata.json et cover.jpg
# ==============================================================================

param(
    [string]$AnimePath = "Y:\Animes"
)

# -- Couleurs console ----------------------------------------------------------
function Write-Info ($msg) { Write-Host "[INFO]  $msg" -ForegroundColor Cyan }
function Write-Ok   ($msg) { Write-Host "[OK]    $msg" -ForegroundColor Green }
function Write-Warn ($msg) { Write-Host "[WARN]  $msg" -ForegroundColor Yellow }
function Write-Err  ($msg) { Write-Host "[ERROR] $msg" -ForegroundColor Red }

if (-not (Test-Path $AnimePath)) {
    Write-Err "Le chemin '$AnimePath' est introuvable."
    exit 1
}

# -- Nettoyage du nom (Logique identique au PHP) -------------------------------
function Format-AnimeName ([string]$raw) {
    $clean = $raw `
        -replace '\[.*?\]', '' `
        -replace '\(.*?\)', '' `
        -replace ' S\d{1,2}$', '' `
        -replace ' Season \d+', '' `
        -replace ' Part \d+', '' `
        -replace '\d{4}$', '' `
        -replace '[_\.]', ' ' `
        -replace '\s{2,}', ' '
    return $clean.Trim()
}

# -- AniList GraphQL Query -----------------------------------------------------
$AniListGraphQL = @'
query ($search: String) {
  Media(search: $search, type: ANIME) {
    id idMal title { romaji english native }
    type format status description(asHtml: false)
    startDate { year month day } season seasonYear episodes
    coverImage { extraLarge large medium color }
    bannerImage genres averageScore trailer { id site }
    studios(isMain: true) { nodes { name } }
  }
}
'@

function Get-AniListData ([string]$animeName) {
    $body = @{ query = $AniListGraphQL; variables = @{ search = $animeName } } | ConvertTo-Json
    try {
        $response = Invoke-RestMethod -Uri "https://graphql.anilist.co" -Method POST -ContentType "application/json" -Body $body
        return $response.data.Media
    }
    catch { return $null }
}

# -- Boucle principale ---------------------------------------------------------
$folders = Get-ChildItem -Path $AnimePath -Directory | Sort-Object Name
Write-Info "$($folders.Count) dossier(s) trouvé(s) dans $AnimePath."

foreach ($folder in $folders) {
    $folderPath = $folder.FullName
    $cleanName = Format-AnimeName $folder.Name
    
    # Noms simplifiés comme dans le script PHP
    $outputFile = Join-Path $folderPath "metadata.json"
    $coverExists = $null -ne (Get-ChildItem -Path $folderPath -Filter "cover.*" -File | Select-Object -First 1)

    if ((Test-Path $outputFile) -and $coverExists) { continue }

    Write-Host ""
    Write-Info "Traitement : '$($folder.Name)'..."
    Start-Sleep -Milliseconds 500 # Rate limit

    $result = Get-AniListData $cleanName

    if ($null -eq $result) {
        Write-Warn "  Aucun résultat trouvé sur AniList pour '$cleanName'"
        continue
    }

    # Sauvegarde du JSON
    if (-not (Test-Path $outputFile)) {
        $metadata = @{ Media = $result } | ConvertTo-Json -Depth 10
        [System.IO.File]::WriteAllLines($outputFile, $metadata)
        Write-Ok "  [JSON]  metadata.json créé."
    }

    # Sauvegarde de la Cover
    if (-not $coverExists) {
        $url = $result.coverImage.extraLarge
        if (-not $url) { $url = $result.coverImage.large }

        if ($url) {
            try {
                $ext = [System.IO.Path]::GetExtension(($url -split '\?')[0])
                if (-not $ext) { $ext = ".jpg" }
                $coverPath = Join-Path $folderPath "cover$ext"
                
                Invoke-WebRequest -Uri $url -OutFile $coverPath -UseBasicParsing
                Write-Ok "  [IMG]   cover$ext enregistrée."
            }
            catch {
                Write-Warn "  [IMG]   Erreur de téléchargement."
            }
        }
    }
}

Write-Host ""
Write-Host "=========================================" -ForegroundColor Magenta
Write-Host " SCAN TERMINÉ AVEC SUCCÈS ! " -ForegroundColor Magenta
Write-Host "=========================================" -ForegroundColor Magenta