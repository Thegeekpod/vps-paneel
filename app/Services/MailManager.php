<?php

namespace App\Services;

use App\Models\EmailDomain;
use App\Models\MailAlias;
use App\Models\Mailbox;

class MailManager
{
    public function __construct(
        protected ServerAgentService $agent
    ) {}

    /**
     * Register an email domain and generate 2048-bit DKIM keys.
     */
    public function createEmailDomain(string $domainName): EmailDomain
    {
        $domainName = strtolower(trim($domainName));

        // Generate RSA 2048-bit DKIM keypair
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        $privateKey = '';
        $publicKey = '';

        if ($res) {
            openssl_pkey_export($res, $privateKey);
            $pubDetails = openssl_pkey_get_details($res);
            $publicKey = $pubDetails['key'] ?? '';
            // Strip headers for DNS TXT format
            $cleanPub = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\r|\n/', '', $publicKey);
        } else {
            $cleanPub = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...';
        }

        return EmailDomain::create([
            'domain_name' => $domainName,
            'status' => 'active',
            'dkim_selector' => 'mail',
            'dkim_private_key' => $privateKey,
            'dkim_public_key' => $cleanPub,
        ]);
    }

    /**
     * Create a virtual mailbox with quota and hashed credentials.
     */
    public function createMailbox(EmailDomain $domain, string $username, string $password, int $quotaMb = 1024): Mailbox
    {
        $cleanUser = strtolower(trim($username));
        $email = "{$cleanUser}@{$domain->domain_name}";

        $result = $this->agent->execute('CreateMailbox', [
            'domain' => $domain->domain_name,
            'username' => $cleanUser,
            'password' => $password,
            'quota_mb' => $quotaMb,
        ], 'Mailbox', $email);

        return Mailbox::create([
            'email_domain_id' => $domain->id,
            'username' => $cleanUser,
            'email' => $email,
            'password_hash' => $result['password_hash'] ?? 'HASHED',
            'quota_mb' => $quotaMb,
            'used_mb' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Create an email alias / forwarding rule.
     */
    public function createAlias(EmailDomain $domain, string $sourceEmail, string $destinationEmail): MailAlias
    {
        return MailAlias::create([
            'email_domain_id' => $domain->id,
            'source_email' => strtolower(trim($sourceEmail)),
            'destination_email' => strtolower(trim($destinationEmail)),
            'is_active' => true,
        ]);
    }

    /**
     * Get security guidance, SPF, DKIM, and DMARC DNS records for an email domain.
     */
    public function getDnsGuidance(EmailDomain $domain, string $serverIp): array
    {
        $d = $domain->domain_name;
        $dkimTxt = "v=DKIM1; k=rsa; p=" . ($domain->dkim_public_key ?: 'YOUR_DKIM_PUBLIC_KEY');

        return [
            'spf' => [
                'type' => 'TXT',
                'name' => '@',
                'value' => "v=spf1 mx a ip4:{$serverIp} ~all",
                'description' => 'Authorizes this server to send email for your domain.',
            ],
            'dkim' => [
                'type' => 'TXT',
                'name' => "{$domain->dkim_selector}._domainkey",
                'value' => $dkimTxt,
                'description' => 'Cryptographic signature to verify emails originated from your server.',
            ],
            'dmarc' => [
                'type' => 'TXT',
                'name' => '_dmarc',
                'value' => 'v=DMARC1; p=quarantine; pct=100; rua=mailto:postmaster@' . $d,
                'description' => 'Instructs receiving mail servers how to treat messages failing SPF/DKIM.',
            ],
            'mx' => [
                'type' => 'MX',
                'name' => '@',
                'value' => "mail.{$d}",
                'priority' => 10,
                'description' => 'Routes incoming emails to your mail server.',
            ],
            'smtp_warnings' => [
                'Outbound Port 25: Many cloud providers (Hetzner, DigitalOcean, AWS, Linode) block outbound port 25 by default. You may need to submit a support ticket to unblock it.',
                'Reverse DNS (rDNS/PTR): Ensure your VPS provider control panel has the PTR record of your IP set to mail.' . $d . '.',
                'Propagation: DNS changes may take up to 24-48 hours to propagate globally.',
            ],
        ];
    }
}
