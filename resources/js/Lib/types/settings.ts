export interface Setting {
    key: string;
    value: string | null;
    value_type: string;
    is_secret: boolean;
    updated_at: string | null;
}
