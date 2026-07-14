export interface Tag {
    id: number;
    name: string;
    color: string;
}

export interface RecipientNote {
    id: number;
    body: string;
    author: string | null;
    created_at: string | null;
}

export interface Recipient {
    id: string;
    email: string;
    first_name: string | null;
    last_name: string | null;
    company_name: string | null;
    source: string;
    status: string;
    custom_fields: Record<string, unknown> | null;
    last_contacted_at: string | null;
    tags: Tag[];
    notes: RecipientNote[];
    created_at: string;
}
