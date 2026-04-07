/**
 * Anexa ?v= para cache bust de arquivos em /public (use com assetVersion do Inertia).
 */
export function publicAsset(path, version) {
    if (!version) {
        return path;
    }
    const sep = path.includes('?') ? '&' : '?';
    return `${path}${sep}v=${encodeURIComponent(version)}`;
}
