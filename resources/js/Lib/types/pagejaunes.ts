export interface PageJaunesEmail {
    external_id: number;
    email: string;
}

export interface PageJaunesCompany {
    external_id: number;
    code: string;
    company_name: string | null;
    abv_company_name: string | null;
    legal_form_label: string | null;
    wilaya_label: string | null;
    locality_label: string | null;
    address: string | null;
    is_distributor: boolean;
    is_exporter: boolean;
    is_importer: boolean;
    is_producer: boolean;
    emails: PageJaunesEmail[];
}
