---
paths:
  - '{config/mail.php,app/Services/FeaturePreviewMailService.php,app/Console/Commands/CheckFeaturePreviewCommand.php,tests/**/*Preview*}'
---

# Console Commands

## Validate the resolved preview mail transport
Preview authentication mail requires certificate- and hostname-verified SMTP over implicit TLS or mandatory STARTTLS. Set require_tls only for preview defaults so main behavior stays unchanged. Check Laravel's actual resolved transport, including MAIL_URL overrides and the MessageSending event's mailer name; checking only mail.default config misses alternate transports. Array mail is permitted only while running unit tests, and tests must never contact a real SMTP service.

## Block legacy SMTP handshake fallback in preview
Symfony's current EsmtpTransport returns from EHLO-to-HELO fallback before checking require_tls. FeaturePreviewSmtpTransport must reject that fallback before authentication, envelope or body writes while preserving normal STARTTLS and implicit TLS. SocketStream::isTLS reports implicit connection mode, not successful STARTTLS state. Keep connection-free simulated handshake regressions for downgrade rejection and successful authenticated STARTTLS.
