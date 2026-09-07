export interface QuickFixAction {
    label: string;
    description: string;
    patch: {
        port?: number;
        encryption?: 'none' | 'ssl' | 'tls';
        host?: string;
    };
}

export interface SmtpDiagnostic {
    category:
        | 'ssl_port_mismatch'
        | 'auth_failed'
        | 'connection_refused'
        | 'timeout'
        | 'dns_not_found'
        | 'cert_error'
        | 'generic';
    title: string;
    cause: string;
    solution: string;
    tips: string[];
    quickFixes?: QuickFixAction[];
}

interface SmtpContext {
    host?: string;
    port?: number;
    encryption?: string;
}

/**
 * Analyzes raw SMTP server responses / exception messages and returns
 * structured diagnostics explaining the root cause, actionable steps to fix it,
 * and quick-fix actions that can be applied to the form in one click.
 */
export function analyzeSmtpError(rawResponse: string, context?: SmtpContext): SmtpDiagnostic {
    const raw = rawResponse || '';
    const port = context?.port;
    const encryption = (context?.encryption || '').toLowerCase();

    // 1. SSL on port 587 mismatch (OpenSSL wrong version number)
    const isSslOn587 =
        raw.includes('0A00010B') ||
        raw.toLowerCase().includes('wrong version number') ||
        (raw.includes('ssl://') && (raw.includes(':587') || port === 587)) ||
        (port === 587 && encryption === 'ssl');

    if (isSslOn587) {
        return {
            category: 'ssl_port_mismatch',
            title: 'Incompatibilité SSL sur le port 587',
            cause: 'Vous tentez une connexion SSL directe (SMTPS) sur le port 587. Le port 587 attend une connexion TCP en clair initiale, puis un échange STARTTLS (chiffrement TLS). En recevant le texte d’accueil SMTP au lieu d’un paquet SSL, OpenSSL échoue avec « wrong version number ».',
            solution: 'Changez le mode de chiffrement en « TLS » pour le port 587. Si votre serveur exige une encapsulation SSL directe dès la connexion, changez le port pour le port standard 465.',
            tips: [
                'Port 587 = Protocole STARTTLS (sélectionnez le chiffrement TLS).',
                'Port 465 = Protocole SSL direct / SMTPS (sélectionnez le chiffrement SSL).',
                'Le port 587 est la norme moderne recommandée pour l’envoi de courriels.',
            ],
            quickFixes: [
                {
                    label: 'Basculer en TLS (Port 587)',
                    description: 'Conserver le port 587 et passer le chiffrement en TLS (recommandé)',
                    patch: { encryption: 'tls', port: 587 },
                },
                {
                    label: 'Passer en SSL (Port 465)',
                    description: 'Conserver le chiffrement SSL et utiliser le port standard 465',
                    patch: { encryption: 'ssl', port: 465 },
                },
            ],
        };
    }

    // 2. TLS on port 465 mismatch
    const isTlsOn465 = port === 465 && (encryption === 'tls' || encryption === 'none');
    if (isTlsOn465) {
        return {
            category: 'ssl_port_mismatch',
            title: 'Incompatibilité de protocole sur le port 465',
            cause: 'Le port 465 attend une encapsulation SSL directe (SMTPS) immédiate. Une tentative de connexion en clair ou de négociation STARTTLS échoue sur ce port.',
            solution: 'Activez le chiffrement « SSL » pour le port 465, ou basculez sur le port 587 avec le chiffrement « TLS ».',
            tips: [
                'Port 465 = SSL direct (SMTPS).',
                'Port 587 = STARTTLS (TLS).',
            ],
            quickFixes: [
                {
                    label: 'Passer en SSL (Port 465)',
                    description: 'Activer le chiffrement SSL direct sur le port 465',
                    patch: { encryption: 'ssl', port: 465 },
                },
                {
                    label: 'Passer en TLS (Port 587)',
                    description: 'Utiliser le port 587 avec STARTTLS',
                    patch: { encryption: 'tls', port: 587 },
                },
            ],
        };
    }

    // 3. Authentication failure (535, invalid credentials)
    const isAuthFailed =
        raw.includes('535') ||
        raw.toLowerCase().includes('authentication failed') ||
        raw.toLowerCase().includes('username and password not accepted') ||
        raw.toLowerCase().includes('badcredentials') ||
        raw.includes('5.7.8') ||
        raw.includes('534-5.7.9');

    if (isAuthFailed) {
        return {
            category: 'auth_failed',
            title: 'Identifiants SMTP refusés',
            cause: 'Le serveur SMTP a rejeté votre nom d’utilisateur ou mot de passe (erreur d’authentification 535).',
            solution: 'Vérifiez soigneusement votre nom d’utilisateur (adresse e-mail complète) et votre mot de passe.',
            tips: [
                'Vérifiez si votre nom d’utilisateur doit inclure le nom de domaine complet (ex: contact@pagesjaunes-dz.com).',
                'Pour Gmail, Google Workspace, Outlook ou Yahoo : vous devez impérativement générer un « Mot de passe d’application » dans la sécurité de votre compte au lieu de votre mot de passe habituel.',
                'Vérifiez que l’accès SMTP / l’authentification SMTP est bien autorisée dans le panneau d’administration de votre messagerie.',
            ],
        };
    }

    // 4. Connection refused (port closed or firewall blocked)
    const isConnectionRefused =
        raw.toLowerCase().includes('connection refused') ||
        raw.includes('code 111') ||
        raw.includes('10061') ||
        raw.toLowerCase().includes('actively refused');

    if (isConnectionRefused) {
        return {
            category: 'connection_refused',
            title: 'Connexion refusée par le serveur',
            cause: 'Aucun service SMTP n’écoute sur cette adresse et ce port, ou le serveur distant rejette activement la connexion.',
            solution: 'Vérifiez l’adresse de l’hôte SMTP et le numéro de port.',
            tips: [
                'Vérifiez l’orthographe du serveur (ex: mail.pagesjaunes-dz.com, smtp.votredomaine.com).',
                'Les ports standards sont 587 (avec TLS) ou 465 (avec SSL). Évitez le port 25 qui est presque toujours bloqué.',
                'Vérifiez que l’adresse IP de votre serveur n’a pas été temporairement bannie par un pare-feu (ex: Fail2ban).',
            ],
            quickFixes:
                port !== 587
                    ? [
                          {
                              label: 'Tester avec le port 587 (TLS)',
                              description: 'Changer pour le port standard 587 avec TLS',
                              patch: { port: 587, encryption: 'tls' },
                          },
                      ]
                    : [
                          {
                              label: 'Tester avec le port 465 (SSL)',
                              description: 'Changer pour le port standard 465 avec SSL',
                              patch: { port: 465, encryption: 'ssl' },
                          },
                      ],
        };
    }

    // 5. Connection timed out
    const isTimeout =
        raw.toLowerCase().includes('timed out') ||
        raw.includes('ETIMEDOUT') ||
        raw.includes('10060') ||
        raw.toLowerCase().includes('operation timed out');

    if (isTimeout) {
        return {
            category: 'timeout',
            title: 'Délai d’attente dépassé (Timeout)',
            cause: 'Le serveur distant ne répond pas dans le délai imparti. Cela indique généralement un blocage réseau, un pare-feu ou un port filtré.',
            solution: 'Vérifiez que le serveur est accessible et qu’aucun pare-feu ne bloque le trafic sortant sur ce port.',
            tips: [
                'Certains hébergeurs cloud bloquent les ports sortants SMTP pour éviter le spam. Assurez-vous que les connexions sortantes sont autorisées.',
                'Si vous utilisez le port 25, passez impérativement au port 587 ou 465.',
                'Vérifiez que le nom de domaine de l’hôte est bien propagé dans les DNS.',
            ],
            quickFixes:
                port !== 587
                    ? [
                          {
                              label: 'Essayer le port 587 (TLS)',
                              description: 'Basculez sur le port 587 (généralement non filtré)',
                              patch: { port: 587, encryption: 'tls' },
                          },
                      ]
                    : undefined,
        };
    }

    // 6. Host not found (DNS error)
    const isDnsError =
        raw.toLowerCase().includes('getaddrinfo') ||
        raw.toLowerCase().includes('name or service not known') ||
        raw.toLowerCase().includes('host not found') ||
        raw.includes('11001') ||
        raw.toLowerCase().includes('no address associated with hostname');

    if (isDnsError) {
        return {
            category: 'dns_not_found',
            title: 'Serveur SMTP introuvable (Erreur DNS)',
            cause: 'Impossible de résoudre l’adresse IP associée au nom d’hôte renseigné.',
            solution: 'Vérifiez l’orthographe exacte du nom d’hôte.',
            tips: [
                'Ne saisissez pas de protocole comme « http:// » ou « ssl:// » dans le champ Hôte.',
                'N’ajoutez pas de numéro de port dans le champ Hôte (le port dispose de son propre champ dédié).',
                'Exemples d’hôtes valides : « mail.pagesjaunes-dz.com », « smtp.gmail.com ».',
            ],
        };
    }

    // 7. SSL Certificate verification failure
    const isCertError =
        raw.toLowerCase().includes('certificate verify failed') ||
        raw.toLowerCase().includes('self-signed') ||
        raw.toLowerCase().includes('local issuer certificate') ||
        raw.toLowerCase().includes('cn name does not match');

    if (isCertError) {
        return {
            category: 'cert_error',
            title: 'Erreur de certificat SSL/TLS',
            cause: 'Le certificat SSL fourni par le serveur SMTP n’a pas pu être validé ou ne correspond pas au nom d’hôte indiqué.',
            solution: 'Assurez-vous que le champ « Hôte » correspond exactement au nom de domaine couvert par le certificat SSL.',
            tips: [
                'Si votre hébergeur utilise un nom de serveur mutualisé (ex: « server123.hebergement.com »), utilisez ce nom d’hôte plutôt que votre sous-domaine.',
                'Vérifiez que le certificat SSL de votre serveur de messagerie n’est pas expiré.',
            ],
        };
    }

    // 8. Generic fallback
    return {
        category: 'generic',
        title: 'Échec de connexion SMTP',
        cause: 'La négociation avec le serveur SMTP a échoué. Consultez le message d’erreur brut ci-dessous pour plus de détails.',
        solution: 'Vérifiez la concordance entre l’Hôte, le Port, le type de Chiffrement et vos identifiants.',
        tips: [
            'Port 587 nécessite le chiffrement TLS.',
            'Port 465 nécessite le chiffrement SSL.',
            'Vérifiez vos paramètres auprès de votre fournisseur de messagerie.',
        ],
    };
}
