# SSL Certificate Renewal — schoolcontrol.net (Namecheap + Let's Encrypt)

> Wildcard certs (`*.schoolcontrol.net`) require **DNS challenge** — Namecheap has no
> official Certbot plugin, so you do this manually. Takes ~5 minutes.

---

## Step 1 — Check current cert status

```bash
sudo certbot certificates
```

Confirm it's expired and note the domains listed.

---

## Step 2 — Run Certbot with DNS challenge

```bash
sudo certbot certonly \
  --manual \
  --preferred-challenges dns \
  -d schoolcontrol.net \
  -d *.schoolcontrol.net
```

Certbot will pause and show something like:

```
Please deploy a DNS TXT record under the name:
_acme-challenge.schoolcontrol.net
with the following value:
aBcDeFgHiJkLmNoPqRsTuVwXyZ123456
```

**Do not press Enter yet.** Go to Step 3 first.

---

## Step 3 — Add TXT record in Namecheap

1. Log in to [namecheap.com](https://namecheap.com)
2. Go to **Domain List** → click **Manage** next to `schoolcontrol.net`
3. Click the **Advanced DNS** tab
4. Click **Add New Record** and fill in:

   | Type | Host | Value | TTL |
   |------|------|-------|-----|
   | TXT Record | `_acme-challenge` | `<value from certbot>` | Automatic |

5. Click the **✓** checkmark to save

> ⚠️ Certbot will give you **two** TXT values (one for the root domain, one for the
> wildcard). Add **both** as separate TXT records under the same host `_acme-challenge`.

---

## Step 4 — Wait for DNS propagation

Wait **60–120 seconds** before continuing. You can verify propagation with:

```bash
dig TXT _acme-challenge.schoolcontrol.net +short
```

The value(s) from Certbot should appear in the output.

Or check online: https://dnschecker.org/#TXT/_acme-challenge.schoolcontrol.net

---

## Step 5 — Complete the Certbot challenge

Go back to your terminal and press **Enter** to continue.

If successful you'll see:

```
Successfully received certificate.
Certificate is saved at: /etc/letsencrypt/live/schoolcontrol.net/fullchain.pem
Key is saved at:         /etc/letsencrypt/live/schoolcontrol.net/privkey.pem
```

---

## Step 6 — Reload Nginx

```bash
sudo nginx -t && sudo nginx -s reload
```

Verify the site loads on HTTPS and the cert shows the new expiry date (90 days from today).

---

## Step 7 — Clean up DNS records

After renewal is confirmed, **delete the `_acme-challenge` TXT records** from Namecheap
Advanced DNS — they're no longer needed and leaving them causes confusion on next renewal.

---

## Step 8 — Set a reminder for next renewal

Let's Encrypt certs expire every **90 days**. Since Namecheap has no Certbot plugin,
auto-renewal won't work for wildcards — set a calendar reminder for **~75 days** from today
to repeat this process.

Alternatively, consider moving DNS to **Cloudflare** (free), which has an official Certbot
plugin that fully automates wildcard renewal:

```bash
# With Cloudflare plugin — fully automated
sudo certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials ~/.secrets/cloudflare.ini \
  -d schoolcontrol.net \
  -d *.schoolcontrol.net
```

---

## Quick Reference — Cert file paths

```
Certificate:  /etc/letsencrypt/live/schoolcontrol.net/fullchain.pem
Private key:  /etc/letsencrypt/live/schoolcontrol.net/privkey.pem
```

These paths already match your Nginx config — no changes needed there after renewal.