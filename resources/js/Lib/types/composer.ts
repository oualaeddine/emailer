export interface Draft {
    id: string;
    subject: string | null;
    html_body: string | null;
    status: string;
    signature_id: string | null;
    updated_at: string | null;
    created_at: string | null;
}

export interface DraftVersion {
    id: number;
    version_number: number;
    subject: string | null;
    html_body: string | null;
    author: string | null;
    created_at: string | null;
}

export interface Signature {
    id: string;
    name: string;
    html_content: string;
    is_default: boolean;
}
