export const createSportMapTileGrid = ({
    keyPrefix,
    mapHeight,
    mapWidth,
    projection,
    tileSize,
    urlForTile,
}) => {
    const zoom = projection.zoom
    const tilesPerAxis = 2 ** zoom
    const startX = Math.floor(projection.left / tileSize) - 1
    const endX = Math.floor((projection.left + mapWidth) / tileSize) + 1
    const startY = Math.floor(projection.top / tileSize) - 1
    const endY = Math.floor((projection.top + mapHeight) / tileSize) + 1
    const tiles = []

    for (let rawX = startX; rawX <= endX; rawX += 1) {
        const x = ((rawX % tilesPerAxis) + tilesPerAxis) % tilesPerAxis

        for (let y = startY; y <= endY; y += 1) {
            if (y < 0 || y >= tilesPerAxis) continue

            tiles.push({
                key: `${keyPrefix}-${zoom}-${rawX}-${y}`,
                url: urlForTile(zoom, x, y),
                style: {
                    left: `${((rawX * tileSize - projection.left) / mapWidth) * 100}%`,
                    top: `${((y * tileSize - projection.top) / mapHeight) * 100}%`,
                    width: `${(tileSize / mapWidth) * 100}%`,
                    height: `${(tileSize / mapHeight) * 100}%`,
                },
            })
        }
    }

    return tiles
}
