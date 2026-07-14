export interface Template {
    id: string;
    name: string;
    category: string | null;
    thumbnail_path: string | null;
    html_content: string;
    is_archived: boolean;
    usage_count: number;
    updated_at: string | null;
}
