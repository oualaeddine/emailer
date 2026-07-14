export interface SmtpAccount {
    id: string;
    name: string;
    provider: string;
    host: string;
    port: number;
    encryption: string;
    username: string;
    from_email: string;
    from_name: string | null;
    daily_quota: number | null;
    hourly_quota: number | null;
    minute_quota: number | null;
    priority: number;
    rotation_weight: number;
    warmup_enabled: boolean;
    health_status: 'healthy' | 'degraded' | 'unhealthy' | 'disabled';
    bounce_rate: number;
    complaint_rate: number;
    is_active: boolean;
    last_tested_at: string | null;
}
