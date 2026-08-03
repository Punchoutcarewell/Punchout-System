import type { Money } from './money';

export interface ProductSummary {
    id: number;
    sku: string;
    name: string;
    categoryName: string | null;
    unspscCode: string;
    listPrice: Money;
    unitOfMeasure: string;
    packSize: number;
    leadTimeDays: number;
    imagePath: string | null;
}

export interface ProductDetail {
    sku: string;
    name: string;
    description: string;
    longDescription: string | null;
    categoryName: string | null;
    unspscCode: string;
    unitOfMeasure: string;
    packSize: number;
    leadTimeDays: number;
    listPrice: Money;
    imagePath: string | null;
    manufacturerName: string | null;
    manufacturerPartId: string | null;
}

export interface CategorySummary {
    id: number;
    name: string;
    slug: string;
    parentId: number | null;
}
