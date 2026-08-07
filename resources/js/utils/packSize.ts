/**
 * A null or 1 packSize means a product is not sold in packs: quantity is
 * a plain unit count and price is per unit. A packSize greater than 1
 * means quantity counts packs, and price is per pack, not per piece,
 * see Catalog\Models\Product::$pack_size on the backend.
 */
export function hasPack(packSize: number | null): boolean {
    return packSize !== null && packSize > 1;
}

export function packQuantityLabel(unitOfMeasure: string, packSize: number | null): string {
    return hasPack(packSize) ? `${unitOfMeasure}, pack of ${packSize}` : unitOfMeasure;
}

export function pricePerLabel(packSize: number | null): string {
    return hasPack(packSize) ? 'per pack' : 'each';
}

/** e.g. "3 packs = 75 pieces total", or null when the product has no pack. */
export function totalPiecesLabel(quantity: number, packSize: number | null): string | null {
    if (!hasPack(packSize)) {
        return null;
    }

    const totalPieces = quantity * (packSize as number);
    const packWord = quantity === 1 ? 'pack' : 'packs';

    return `${quantity} ${packWord} = ${totalPieces.toLocaleString()} pieces total`;
}
