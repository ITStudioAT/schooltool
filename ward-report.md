# Ward Security Report

**Project:** laravel/laravel  
**Laravel:** ^12.34  
**PHP:** ^8.2  
**Duration:** 14.958s  
**Scanners:** env-scanner, config-scanner, dependency-scanner, rules-scanner  

## Summary

| Total | 357 |
|-------|---|
| 🟠 High | 96 |
| 🟡 Medium | 8 |
| 🟢 Low | 253 |

## Findings

### 🟠 High (96)

#### ENV-002 — APP_DEBUG is enabled

- **File:** `.env:4`
- **Category:** Configuration
- **Scanner:** env-scanner

APP_DEBUG is set to true. In production, this exposes detailed error messages including stack traces, database queries, and environment variables to end users.

```
APP_DEBUG=true
```

**Remediation:**

Set APP_DEBUG=false in your production .env file. Use Laravel's logging system for error tracking instead.

**References:**
- https://owasp.org/Top10/A05_2021-Security_Misconfiguration/

---

#### CFG-010 — CORS allows credentials with wildcard origin

- **File:** `config/cors.php:32`
- **Category:** Configuration
- **Scanner:** config-scanner

CORS is configured with both 'supports_credentials' => true and wildcard allowed_origins. This combination allows any website to make authenticated requests to your API.

```
'supports_credentials' => true,
```

**Remediation:**

Never combine 'supports_credentials' => true with wildcard origins. Specify exact allowed origins.

**References:**
- https://cwe.mitre.org/data/definitions/942.html

---

#### CVE-2025-54370 — [CVE-2025-54370] phpoffice/phpspreadsheet@4.5.0 — PhpSpreadsheet vulnerable to SSRF when reading and displaying a processed HTML document in the browser

- **File:** `composer.lock:0`
- **Category:** Dependencies
- **Scanner:** dependency-scanner

PhpSpreadsheet vulnerable to SSRF when reading and displaying a processed HTML document in the browser

**Remediation:**

Upgrade phpoffice/phpspreadsheet to 1.30.0 or later:
  composer require phpoffice/phpspreadsheet:1.30.0

**References:**
- https://github.com/PHPOffice/PhpSpreadsheet/security/advisories/GHSA-rx7m-68vc-ppxh
- https://nvd.nist.gov/vuln/detail/CVE-2025-54370
- https://github.com/PHPOffice/PhpSpreadsheet/commit/334a67797ace574d1d37c0992ffe283b7415471a

---

#### CRYPTO-001 — md5() used for hashing

- **File:** `app\Http\Controllers\Admin\GroupController.php:1319`
- **Category:** Cryptography
- **Scanner:** rules-scanner

md5() is cryptographically broken — collisions can be generated in seconds. It must not be used for password hashing, integrity checks, or any security-sensitive operation.


```
'id' => $contactId !== '' ? $contactId : md5((string) json_encode($contact)),
```

**Remediation:**

For passwords: use Hash::make() (bcrypt/argon2)
  $hash = Hash::make($password);
For integrity: use hash('sha256', $data) or HMAC
  $hash = hash_hmac('sha256', $data, $key);


**References:**
- https://cwe.mitre.org/data/definitions/328.html

---

#### CRYPTO-001 — md5() used for hashing

- **File:** `app\Http\Controllers\Admin\GroupController.php:2207`
- **Category:** Cryptography
- **Scanner:** rules-scanner

md5() is cryptographically broken — collisions can be generated in seconds. It must not be used for password hashing, integrity checks, or any security-sensitive operation.


```
return 'fallback:'.md5(implode('|', [
```

**Remediation:**

For passwords: use Hash::make() (bcrypt/argon2)
  $hash = Hash::make($password);
For integrity: use hash('sha256', $data) or HMAC
  $hash = hash_hmac('sha256', $data, $key);


**References:**
- https://cwe.mitre.org/data/definitions/328.html

---

#### CRYPTO-001 — md5() used for hashing

- **File:** `database\factories\SchoolFactory.php:21`
- **Category:** Cryptography
- **Scanner:** rules-scanner

md5() is cryptographically broken — collisions can be generated in seconds. It must not be used for password hashing, integrity checks, or any security-sensitive operation.


```
'short_name'    => strtoupper(substr(md5(uniqid()), 0, 3)),
```

**Remediation:**

For passwords: use Hash::make() (bcrypt/argon2)
  $hash = Hash::make($password);
For integrity: use hash('sha256', $data) or HMAC
  $hash = hash_hmac('sha256', $data, $key);


**References:**
- https://cwe.mitre.org/data/definitions/328.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:182`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$isSent = rand(0, 100) > 30; // 70% wurden gesendet
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:183`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$isSeen = $isSent && rand(0, 100) > 40; // 60% der gesendeten wurden gesehen
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:193`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$sentAt = now()->subDays(rand(1, 30));
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:200`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$seenAt = $sentAt->copy()->addHours(rand(1, 48));
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:209`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'message' => $this->requestMessages[array_rand($this->requestMessages)],
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:210`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'is_serious' => rand(0, 100) > 20,
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:218`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'created_at' => now()->subDays(rand(1, 60)),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:264`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$isSent = rand(0, 100) > 30; // 70% wurden gesendet
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:265`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$isSeen = $isSent && rand(0, 100) > 40; // 60% der gesendeten wurden gesehen
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:275`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$sentAt = now()->subDays(rand(1, 30));
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:282`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$seenAt = $sentAt->copy()->addHours(rand(1, 48));
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:291`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'message' => $this->requestMessages[array_rand($this->requestMessages)],
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:292`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'is_serious' => rand(0, 100) > 20,
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:300`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'created_at' => now()->subDays(rand(1, 60)),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:327`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$mustBeAccepted = rand(0, 1);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:328`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$acceptedAt = $mustBeAccepted ? now()->subDays(rand(1, 30)) : null;
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:351`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'price_per_hour' => rand(10, 25),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:352`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'is_group' => rand(0, 1),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:353`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'max_group_members' => rand(0, 1) ? rand(2, 5) : null,
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Console\Commands\TutoringTestDataCommand.php:356`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'created_at' => now()->subDays(rand(1, 60)),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Services\AdminService.php:218`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$token = rand(100000, 999999);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Services\AdminService.php:228`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$token = rand(100000, 999999);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `app\Services\AdminService.php:238`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$token = rand(100000, 999999);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\factories\SchoolFactory.php:20`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'long_name'     => 'Test Schule ' . rand(100, 999),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\factories\SchoolFactory.php:22`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'email'         => 'school' . rand(1000, 9999) . '@test.local',
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\factories\SchoolFactory.php:24`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'is_selectable' => rand(0, 10) > 1, // 90% true
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:316`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'tutoring_student_must_be_confirmed' => rand(1, 10) <= 8 ? 1 : 0, // 80% müssen bestätigt werden
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:318`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'tutoring_max_offers_per_student' => rand(3, 5), // 3-5 Angebote pro Student
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:336`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$mentorCount = min(rand(2, 4), count($teachers));
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:346`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'must_be_accepted' => rand(0, 10) > 3, // 70% müssen bestätigt werden
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:366`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$gender = rand(0, 1) ? 'männlich' : 'weiblich';
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:367`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$firstName = $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:368`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$lastName = $this->lastNames[array_rand($this->lastNames)];
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:371`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$sexRand = rand(1, 100);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:394`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$shortLength = rand(3, 4);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:427`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$gender = rand(0, 1) ? 'männlich' : 'weiblich';
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:428`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$firstName = $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:429`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$lastName = $this->lastNames[array_rand($this->lastNames)];
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:432`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$sexRand = rand(1, 100);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:489`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$subject = $subjects[array_rand($subjects)];
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:492`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$statusRand = rand(1, 100);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:521`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$numClasses = rand(1, 4);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:535`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$mentorEmail = $subject->email_mentors[array_rand($subject->email_mentors)];
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:543`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$acceptedAt = $isConfirmed ? now()->subDays(rand(1, 30))->format('Y-m-d') : null;
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:554`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'active_until' => now()->addMonths(rand(1, 6))->format('Y-m-d'),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:556`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'price_per_hour' => rand(10, 25),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:557`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'is_group' => rand(0, 10) > 7, // 30% Gruppenunterricht
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:558`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'max_group_members' => rand(0, 10) > 7 ? rand(2, 5) : null,
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:562`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'click_count' => rand(0, 50),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:563`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'visible_for_other_schools' => rand(0, 1) === 1, // 50% sichtbar für andere Schulen
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:580`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$numDays = rand(2, 4); // 2-4 Tage pro Woche verfügbar
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:588`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'from' => sprintf('%02d:00', rand(14, 17)),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSeeder.php:589`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'to' => sprintf('%02d:00', rand(16, 19)),
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSmallSeeder.php:90`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$sexRand = rand(1, 100);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSmallSeeder.php:121`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$mentorCount = min(rand(1, 2), count($teachers));
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSmallSeeder.php:141`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$gender = rand(0, 1) ? 'männlich' : 'weiblich';
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSmallSeeder.php:142`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$firstName = $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSmallSeeder.php:143`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$lastName = $this->lastNames[array_rand($this->lastNames)];
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSmallSeeder.php:146`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$sexRand = rand(1, 100);
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSmallSeeder.php:175`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$subject = $subjectModels[array_rand($subjectModels)];
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSmallSeeder.php:203`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
$mentorEmail = $subject->email_mentors[array_rand($subject->email_mentors)];
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### CRYPTO-003 — Weak random number generation

- **File:** `database\seeders\TutoringTestDataSmallSeeder.php:224`
- **Category:** Cryptography
- **Scanner:** rules-scanner

rand() and mt_rand() are not cryptographically secure. Using them for tokens, passwords, OTPs, or nonces makes these values predictable.


```
'visible_for_other_schools' => rand(0, 1) === 1, // 50% sichtbar für andere Schulen
```

**Remediation:**

Use cryptographically secure alternatives:
  random_int($min, $max)         // secure integer
  Str::random(40)                // secure string (Laravel)
  random_bytes(32)               // secure raw bytes


**References:**
- https://cwe.mitre.org/data/definitions/330.html

---

#### INJECT-001 — DB::raw() with variable interpolation

- **File:** `app\Http\Controllers\Admin\GroupController.php:2404`
- **Category:** Injection
- **Scanner:** rules-scanner

DB::raw() is called with a PHP variable, which may lead to SQL injection if the variable contains unsanitized user input. Laravel's query builder automatically escapes parameters — use bindings instead of raw SQL.


```
->whereIn(DB::raw('LOWER(TRIM(email))'), $teacherEmails->all())
```

**Remediation:**

Use parameter bindings:
  DB::raw('COUNT(*) as count')           // safe — no variables
  DB::select('SELECT * FROM users WHERE id = ?', [$id])  // safe — bound
Avoid:
  DB::raw("WHERE name = '$name'")        // vulnerable


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-001 — DB::raw() with variable interpolation

- **File:** `app\Http\Controllers\Admin\GroupController.php:2454`
- **Category:** Injection
- **Scanner:** rules-scanner

DB::raw() is called with a PHP variable, which may lead to SQL injection if the variable contains unsanitized user input. Laravel's query builder automatically escapes parameters — use bindings instead of raw SQL.


```
->whereIn(DB::raw('LOWER(TRIM(email))'), $teacherRowsByEmail->keys()->all())
```

**Remediation:**

Use parameter bindings:
  DB::raw('COUNT(*) as count')           // safe — no variables
  DB::select('SELECT * FROM users WHERE id = ?', [$id])  // safe — bound
Avoid:
  DB::raw("WHERE name = '$name'")        // vulnerable


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\GroupController.php:1707`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeGroupName($this->defaultAllSchoolMembersGroupName())])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\GroupController.php:1774`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(TRIM(email)) = ?', [$this->normalizeEmail((string) ($entry['email'] ?? ''))])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\GroupController.php:2346`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedTarget])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\GroupController.php:2356`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeGroupName('Teacher')])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\GroupController.php:2788`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\GroupController.php:3553`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\Materials\MaterialShareController.php:2317`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedType)])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\Materials\MaterialShareController.php:2367`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(value) = ?', [mb_strtolower($statusValue)])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\Materials\MaterialShareController.php:3711`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(email) = ?', [$email])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\Materials\MaterialShareController.php:3805`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(email) = ?', [mb_strtolower($userEmail)])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Http\Controllers\Admin\Teaching\TeachingCourseController.php:31`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
'teachingCourseDates' => fn ($q) => $q->orderBy('date')->orderByRaw('JSON_EXTRACT(hours, "$[0]")'),
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Services\Materials\MaterialService.php:1623`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('LOWER(name) = ?', [mb_strtolower($normalized)])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-002 — Raw query methods with variable concatenation

- **File:** `app\Services\TeachingCourseDateService.php:34`
- **Category:** Injection
- **Scanner:** rules-scanner

A raw query method (whereRaw, selectRaw, havingRaw, orderByRaw, groupByRaw) uses string concatenation or interpolation with a variable. This can lead to SQL injection.


```
->whereRaw('JSON_CONTAINS(hours, ?)', [json_encode($hours[0])])
```

**Remediation:**

Pass bindings as the second argument:
  ->whereRaw('price > ?', [$minPrice])
Or use the query builder:
  ->where('price', '>', $minPrice)


**References:**
- https://cwe.mitre.org/data/definitions/89.html

---

#### INJECT-003 — Shell command execution function

- **File:** `app\Console\Commands\QueueHealthCheck.php:34`
- **Category:** Injection
- **Scanner:** rules-scanner

PHP shell execution functions (exec, system, shell_exec, passthru, popen, proc_open) are being used. If any argument includes user input, this leads to OS command injection.


```
Log::warning('Queue health check skipped because exec() is unavailable.');
```

**Remediation:**

Avoid shell commands when possible. If necessary:
  - Use escapeshellarg() and escapeshellcmd() for all arguments
  - Use Symfony\Component\Process\Process for safer execution
  - Validate and whitelist expected input values


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/78.html

---

#### INJECT-003 — Shell command execution function

- **File:** `app\Console\Commands\QueueHealthCheck.php:145`
- **Category:** Injection
- **Scanner:** rules-scanner

PHP shell execution functions (exec, system, shell_exec, passthru, popen, proc_open) are being used. If any argument includes user input, this leads to OS command injection.


```
pclose(popen($command, 'r'));
```

**Remediation:**

Avoid shell commands when possible. If necessary:
  - Use escapeshellarg() and escapeshellcmd() for all arguments
  - Use Symfony\Component\Process\Process for safer execution
  - Validate and whitelist expected input values


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/78.html

---

#### INJECT-003 — Shell command execution function

- **File:** `app\Console\Commands\QueueHealthCheck.php:232`
- **Category:** Injection
- **Scanner:** rules-scanner

PHP shell execution functions (exec, system, shell_exec, passthru, popen, proc_open) are being used. If any argument includes user input, this leads to OS command injection.


```
exec($command, $output);
```

**Remediation:**

Avoid shell commands when possible. If necessary:
  - Use escapeshellarg() and escapeshellcmd() for all arguments
  - Use Symfony\Component\Process\Process for safer execution
  - Validate and whitelist expected input values


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/78.html

---

#### INJECT-003 — Shell command execution function

- **File:** `app\Http\Controllers\Admin\LogController.php:160`
- **Category:** Injection
- **Scanner:** rules-scanner

PHP shell execution functions (exec, system, shell_exec, passthru, popen, proc_open) are being used. If any argument includes user input, this leads to OS command injection.


```
Log::info('queue:health-check skipped during queue restart because exec() is unavailable.');
```

**Remediation:**

Avoid shell commands when possible. If necessary:
  - Use escapeshellarg() and escapeshellcmd() for all arguments
  - Use Symfony\Component\Process\Process for safer execution
  - Validate and whitelist expected input values


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/78.html

---

#### INJECT-003 — Shell command execution function

- **File:** `public\reset-opcache.php:44`
- **Category:** Injection
- **Scanner:** rules-scanner

PHP shell execution functions (exec, system, shell_exec, passthru, popen, proc_open) are being used. If any argument includes user input, this leads to OS command injection.


```
passthru("cd $baseDir && php artisan cache:clear", $exitCode);
```

**Remediation:**

Avoid shell commands when possible. If necessary:
  - Use escapeshellarg() and escapeshellcmd() for all arguments
  - Use Symfony\Component\Process\Process for safer execution
  - Validate and whitelist expected input values


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/78.html

---

#### INJECT-003 — Shell command execution function

- **File:** `public\reset-opcache.php:48`
- **Category:** Injection
- **Scanner:** rules-scanner

PHP shell execution functions (exec, system, shell_exec, passthru, popen, proc_open) are being used. If any argument includes user input, this leads to OS command injection.


```
passthru("cd $baseDir && php artisan config:clear", $exitCode);
```

**Remediation:**

Avoid shell commands when possible. If necessary:
  - Use escapeshellarg() and escapeshellcmd() for all arguments
  - Use Symfony\Component\Process\Process for safer execution
  - Validate and whitelist expected input values


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/78.html

---

#### INJECT-003 — Shell command execution function

- **File:** `public\reset-opcache.php:52`
- **Category:** Injection
- **Scanner:** rules-scanner

PHP shell execution functions (exec, system, shell_exec, passthru, popen, proc_open) are being used. If any argument includes user input, this leads to OS command injection.


```
passthru("cd $baseDir && php artisan route:clear", $exitCode);
```

**Remediation:**

Avoid shell commands when possible. If necessary:
  - Use escapeshellarg() and escapeshellcmd() for all arguments
  - Use Symfony\Component\Process\Process for safer execution
  - Validate and whitelist expected input values


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/78.html

---

#### INJECT-003 — Shell command execution function

- **File:** `tests\Feature\Console\QueueHealthCheckTest.php:4`
- **Category:** Injection
- **Scanner:** rules-scanner

PHP shell execution functions (exec, system, shell_exec, passthru, popen, proc_open) are being used. If any argument includes user input, this leads to OS command injection.


```
function exec(string $command, ?array &$output = null): int
```

**Remediation:**

Avoid shell commands when possible. If necessary:
  - Use escapeshellarg() and escapeshellcmd() for all arguments
  - Use Symfony\Component\Process\Process for safer execution
  - Validate and whitelist expected input values


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/78.html

---

#### INJECT-003 — Shell command execution function

- **File:** `tests\Feature\Console\QueueHealthCheckTest.php:40`
- **Category:** Injection
- **Scanner:** rules-scanner

PHP shell execution functions (exec, system, shell_exec, passthru, popen, proc_open) are being used. If any argument includes user input, this leads to OS command injection.


```
function popen(string $command, string $mode)
```

**Remediation:**

Avoid shell commands when possible. If necessary:
  - Use escapeshellarg() and escapeshellcmd() for all arguments
  - Use Symfony\Component\Process\Process for safer execution
  - Validate and whitelist expected input values


**References:**
- https://owasp.org/Top10/A03_2021-Injection/
- https://cwe.mitre.org/data/definitions/78.html

---

#### INJECT-005 — Unsafe deserialization with unserialize()

- **File:** `tests\Unit\PrintRegisterDateJobTest.php:238`
- **Category:** Injection
- **Scanner:** rules-scanner

unserialize() on untrusted data can lead to object injection attacks, potentially allowing remote code execution through PHP magic methods (__wakeup, __destruct, __toString).


```
$unserialized = unserialize($serialized);
```

**Remediation:**

Use json_decode() instead of unserialize() for data interchange.
If unserialize() is required, use the allowed_classes option:
  unserialize($data, ['allowed_classes' => [MyClass::class]]);


**References:**
- https://owasp.org/Top10/A08_2021-Software_and_Data_Integrity_Failures/
- https://cwe.mitre.org/data/definitions/502.html

---

#### INJECT-005 — Unsafe deserialization with unserialize()

- **File:** `tests\Unit\PrintRegisterExcelJobTest.php:269`
- **Category:** Injection
- **Scanner:** rules-scanner

unserialize() on untrusted data can lead to object injection attacks, potentially allowing remote code execution through PHP magic methods (__wakeup, __destruct, __toString).


```
$unserialized = unserialize($serialized);
```

**Remediation:**

Use json_decode() instead of unserialize() for data interchange.
If unserialize() is required, use the allowed_classes option:
  unserialize($data, ['allowed_classes' => [MyClass::class]]);


**References:**
- https://owasp.org/Top10/A08_2021-Software_and_Data_Integrity_Failures/
- https://cwe.mitre.org/data/definitions/502.html

---

#### INJECT-005 — Unsafe deserialization with unserialize()

- **File:** `tests\Unit\PrintRegisterSupervisorJobTest.php:267`
- **Category:** Injection
- **Scanner:** rules-scanner

unserialize() on untrusted data can lead to object injection attacks, potentially allowing remote code execution through PHP magic methods (__wakeup, __destruct, __toString).


```
$unserialized = unserialize($serialized);
```

**Remediation:**

Use json_decode() instead of unserialize() for data interchange.
If unserialize() is required, use the allowed_classes option:
  unserialize($data, ['allowed_classes' => [MyClass::class]]);


**References:**
- https://owasp.org/Top10/A08_2021-Software_and_Data_Integrity_Failures/
- https://cwe.mitre.org/data/definitions/502.html

---

#### CONFIG-004 — Empty $guarded array on Eloquent model

- **File:** `app\Models\TutoringOfferRequest.php:59`
- **Category:** Configuration
- **Scanner:** rules-scanner

Setting $guarded = [] makes every attribute mass-assignable. An attacker can inject unexpected fields (is_admin, role, email_verified_at) through mass assignment via create() or update().


```
protected $guarded = [];
```

**Remediation:**

Use $fillable to explicitly list allowed fields:
  protected $fillable = ['name', 'email', 'password'];
Or set $guarded to protect sensitive fields:
  protected $guarded = ['id', 'is_admin', 'role'];


**References:**
- https://owasp.org/Top10/A04_2021-Insecure_Design/
- https://cwe.mitre.org/data/definitions/915.html

---

### 🟡 Medium (8)

#### ENV-005 — APP_ENV is set to 'local'

- **File:** `.env:2`
- **Category:** Configuration
- **Scanner:** env-scanner

The application environment suggests a non-production configuration. If this is a production server, this may cause debug features to be enabled and performance optimizations to be skipped.

```
APP_ENV=local
```

**Remediation:**

Set APP_ENV=production on production servers.

---

#### CFG-009 — CORS allows all origins

- **File:** `config/cors.php:22`
- **Category:** Configuration
- **Scanner:** config-scanner

config/cors.php allows requests from any origin ('*'). This permits cross-site data theft if authenticated endpoints return sensitive data.

```
'allowed_origins' => ['*'],
```

**Remediation:**

Specify allowed origins: 'allowed_origins' => [env('FRONTEND_URL')],

**References:**
- https://cwe.mitre.org/data/definitions/942.html

---

#### CVE-2026-30838 — [CVE-2026-30838] league/commonmark@2.8.0 — CommonMark has DisallowedRawHtml extension bypass via whitespace in HTML tag names

- **File:** `composer.lock:0`
- **Category:** Dependencies
- **Scanner:** dependency-scanner

CommonMark has DisallowedRawHtml extension bypass via whitespace in HTML tag names

**Remediation:**

Upgrade league/commonmark to 2.8.1 or later:
  composer require league/commonmark:2.8.1

**References:**
- https://github.com/thephpleague/commonmark/security/advisories/GHSA-4v6x-c7xx-hw9f
- https://commonmark.thephpleague.com/extensions/disallowed-raw-html

---

#### DEBUG-002 — dump() call left in code

- **File:** `config\debugbar.php:208`
- **Category:** Debug
- **Scanner:** rules-scanner

dump() outputs variable contents to the browser without stopping execution. In production this leaks internal data structures to end users.


```
'capture_dumps' => env('DEBUGBAR_OPTIONS_MESSAGES_CAPTURE_DUMPS', false), // Capture laravel `dump();` as message
```

**Remediation:**

Remove dump() calls. Use Log::info() or Log::debug() for runtime inspection.


**References:**
- https://cwe.mitre.org/data/definitions/215.html

---

#### DEBUG-005 — Debug bar or Clockwork left enabled

- **File:** `config\debugbar.php:42`
- **Category:** Debug
- **Scanner:** rules-scanner

Debug toolbar packages (Laravel Debugbar, Clockwork) are useful in development but expose query logs, route information, session data, and request details in production.


```
'enabled'    => env('DEBUGBAR_STORAGE_ENABLED', true),
```

**Remediation:**

Ensure debug tools are environment-gated:
  'enabled' => env('DEBUGBAR_ENABLED', false),
And never set DEBUGBAR_ENABLED=true in production .env.


---

#### CONFIG-001 — Wildcard CORS origin allowed

- **File:** `config\cors.php:22`
- **Category:** Configuration
- **Scanner:** rules-scanner

The CORS configuration allows all origins ('*'). This permits any website to make authenticated cross-origin requests to your API, potentially exposing user data through CSRF-like attacks.


```
'allowed_origins' => ['*'],
```

**Remediation:**

Specify explicit allowed origins:
  'allowed_origins' => [env('FRONTEND_URL', 'https://myapp.com')],


**References:**
- https://cwe.mitre.org/data/definitions/942.html

---

#### CONFIG-007 — Permissive validation — no size or type constraints

- **File:** `app\Http\Resources\Admin\Materials\MaterialCardAttachmentResource.php:23`
- **Category:** Configuration
- **Scanner:** rules-scanner

A file upload field uses the 'file' validation rule without size limits or type restrictions. This allows any file type and size, risking storage abuse and malicious file uploads.


```
'preview_url' => $this->attachment_type === 'file'
```

**Remediation:**

Always set mimes and max size:
  'avatar' => 'required|file|mimes:jpg,png|max:2048',


---

#### CONFIG-007 — Permissive validation — no size or type constraints

- **File:** `app\Http\Resources\Admin\Materials\MaterialCardAttachmentResource.php:26`
- **Category:** Configuration
- **Scanner:** rules-scanner

A file upload field uses the 'file' validation rule without size limits or type restrictions. This allows any file type and size, risking storage abuse and malicious file uploads.


```
'download_url' => $this->attachment_type === 'file'
```

**Remediation:**

Always set mimes and max size:
  'avatar' => 'required|file|mimes:jpg,png|max:2048',


---

### 🟢 Low (253)

#### ENV-006 — Database password is empty

- **File:** `.env:47`
- **Category:** Configuration
- **Scanner:** env-scanner

DB_PASSWORD is set to an empty string. While this may be valid for local development with trust authentication, it's a security risk if this configuration reaches production.

```
DB_PASSWORD=
```

**Remediation:**

Set a strong database password for non-local environments.

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:21`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/routes/is_route_allowed', [RouteController::class, 'isRouteAllowed']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:22`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/execute_logout', [AdminController::class, 'executeLogout']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:25`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/config', [HomepageController::class, 'config']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:26`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/load_schools_for_tool', [HomepageController::class, 'loadSchoolsForTool']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:27`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/logout', [\App\Http\Controllers\Homepage\HomepageController::class, 'logout']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:30`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/student/config', [\App\Http\Controllers\Student\StudentController::class, 'config']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:34`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/student/user', [\App\Http\Controllers\Student\StudentController::class, 'user'])->middleware('tool-licensed:Lehrertool');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:36`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/student/courses', [\App\Http\Controllers\Student\CourseController::class, 'index'])->middleware('tool-licensed:Lehrertool');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:37`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/student/courses/{courseId}', [\App\Http\Controllers\Student\CourseController::class, 'show'])->middleware('tool-licensed:Lehrertool');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:38`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/student/courses/{courseId}/entries', [\App\Http\Controllers\Student\CourseStudentEntryController::class, 'index'])->middleware('tool-licensed:Lehrertool');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:41`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/config', [AdminController::class, 'config']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:48`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/new_teacher_step_school', [AdminController::class, 'newTeacherStepSchool']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:49`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/new_teacher_step_code', [AdminController::class, 'newTeacherStepCode']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:76`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/tutoring/config', [\App\Http\Controllers\Tutoring\TutoringController::class, 'config']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:79`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/create_user', [\App\Http\Controllers\Tutoring\TutoringController::class, 'createUser'])->middleware('tool-licensed:Nachhilfetool');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:83`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/tutoring/load_offer_config', [\App\Http\Controllers\Tutoring\OfferController::class, 'loadOfferConfig']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:84`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/tutoring/load_offers', [\App\Http\Controllers\Tutoring\OfferController::class, 'loadOffers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:85`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/click_count', [\App\Http\Controllers\Tutoring\OfferController::class, 'clickCount']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:86`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/set_user_search_criteria', [\App\Http\Controllers\Tutoring\OfferController::class, 'setUserSearchCriteria']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:90`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/impersonation/status', [\App\Http\Controllers\Admin\ImpersonationController::class, 'status']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:95`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/navigation/profile_menu', [NavigationController::class, 'profileMenu']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:96`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/navigation/user_menu', [NavigationController::class, 'userMenu']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:100`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/impersonation/stop', [\App\Http\Controllers\Admin\ImpersonationController::class, 'stop']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:102`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/test-queue', [App\Http\Controllers\Admin\HealthController::class, 'testQueue']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:103`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/test-queue/check', [App\Http\Controllers\Admin\HealthController::class, 'checkQueueStatus']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:104`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/test-cron/check', [App\Http\Controllers\Admin\HealthController::class, 'testCron']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:109`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/users/update_profile/{user}', [UserController::class, 'updateProfile']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:110`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users/update_with_code', [UserController::class, 'updateWithCode']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:121`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/users20/load_users', [\App\Http\Controllers\Admin\UserController::class, 'loadUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:122`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users20/update', [\App\Http\Controllers\Admin\UserController::class, 'updateUser']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:123`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users20/store', [\App\Http\Controllers\Admin\UserController::class, 'storeUser']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:124`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users20/delete_users', [\App\Http\Controllers\Admin\UserController::class, 'deleteUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:128`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/teachers/delete_teachers', [\App\Http\Controllers\Admin\TeacherController::class, 'deleteTeachers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:130`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/teachers_list/delete_teachers', [\App\Http\Controllers\Admin\TeachersListController::class, 'deleteTeachers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:132`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/teachers_list_upload', [\App\Http\Controllers\Admin\TeachersListController::class, 'upload']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:133`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/teachers_list_upload', [\App\Http\Controllers\Admin\TeachersListController::class, 'uploadNext']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:136`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/school_tools/set_active_schoolyear', [\App\Http\Controllers\Admin\SchoolToolController::class, 'setActiveSchoolyear']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:141`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/teaching_upload/{slug}', [\App\Http\Controllers\Admin\Teaching\FileUploadController::class, 'upload']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:142`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/teaching_upload/{slug}', [\App\Http\Controllers\Admin\Teaching\FileUploadController::class, 'uploadNext']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:149`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/logout', [\App\Http\Controllers\Tutoring\UserController::class, 'logout']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:150`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/tutoring/load_auth', [\App\Http\Controllers\Tutoring\TutoringController::class, 'loadAuth']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:151`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/tutoring/load_my_offers', [\App\Http\Controllers\Tutoring\OfferController::class, 'loadMyOffers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:154`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/toggle_offer', [\App\Http\Controllers\Tutoring\OfferController::class, 'toggleOffer']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:155`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/send_request', [\App\Http\Controllers\Tutoring\OfferController::class, 'sendRequest']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:157`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/tutoring/received_offer_requests', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'receivedRequests']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:158`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/request_mail_clicked', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'requestMailClicked']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:159`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/to_archive', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'toArchive']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:160`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/to_active', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'toActive']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:161`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/to_user_archive', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'toUserArchive']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:162`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/homepage/tutoring/to_user_active', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'toUserActive']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:169`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/school_tools/load_config', [\App\Http\Controllers\Admin\SchoolToolController::class, 'loadConfig']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:170`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users20/toggle_is_active', [\App\Http\Controllers\Admin\UserController::class, 'toggleIsActive']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:176`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/groups/{group}/members', [\App\Http\Controllers\Admin\GroupController::class, 'members']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:177`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/groups/{group}/source-members', [\App\Http\Controllers\Admin\GroupController::class, 'sourceMembers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:178`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/groups/{group}/members/{member}', [\App\Http\Controllers\Admin\GroupController::class, 'removeMember']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:179`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/groups/{group}/remove-users', [\App\Http\Controllers\Admin\GroupController::class, 'removeMembers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:180`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/groups/{group}/assignable-users', [\App\Http\Controllers\Admin\GroupController::class, 'assignableUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:181`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/groups/{group}/assign-users', [\App\Http\Controllers\Admin\GroupController::class, 'assignUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:182`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/groups/{group}/assignable-groups', [\App\Http\Controllers\Admin\GroupController::class, 'assignableGroups']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:183`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/groups/{group}/assign-from-group', [\App\Http\Controllers\Admin\GroupController::class, 'assignFromGroup']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:184`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/groups/{group}/my-teaching-courses', [\App\Http\Controllers\Admin\GroupController::class, 'myTeachingCourses']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:192`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/school_tools/save_tutoring_settings', [\App\Http\Controllers\Admin\SchoolToolController::class, 'saveTutoringSettings']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:195`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/tutoring/create_subjects', [\App\Http\Controllers\Admin\Tutoring\SubjectController::class, 'createSubjects']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:196`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/tutoring/delete_users', [\App\Http\Controllers\Admin\Tutoring\UserController::class, 'deleteUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:197`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/tutoring/clean_users', [\App\Http\Controllers\Admin\Tutoring\UserController::class, 'cleanUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:198`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/tutoring/confirm_users', [\App\Http\Controllers\Admin\Tutoring\UserController::class, 'confirmUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:203`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/teaching/search116', [\App\Http\Controllers\Admin\Teaching\TeachingController::class, 'search116']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:204`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/teaching/load_settings', [\App\Http\Controllers\Admin\Teaching\TeachingController::class, 'loadSettings']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:205`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/teaching/save_settings', [\App\Http\Controllers\Admin\Teaching\TeachingController::class, 'saveSettings']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:206`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/teaching/save_active_semester', [\App\Http\Controllers\Admin\Teaching\TeachingController::class, 'saveActiveSemester']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:207`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/teaching/save_semester_2_date', [\App\Http\Controllers\Admin\Teaching\TeachingController::class, 'saveSemester2Date']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:210`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/teaching/course_dates/{course_date}/status', [\App\Http\Controllers\Admin\Teaching\CourseDateController::class, 'updateStatus']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:216`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/teaching/load_class_students', [\App\Http\Controllers\Admin\Teaching\StudentController::class, 'loadClassStudents']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:217`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/teaching/import116/load_class_students', [\App\Http\Controllers\Admin\Teaching\Import116Controller::class, 'loadClassStudents']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:218`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/teaching/import116/runs', [\App\Http\Controllers\Admin\Teaching\Import116Controller::class, 'runs']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:219`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/teaching/import116/runs/{import116_run}', [\App\Http\Controllers\Admin\Teaching\Import116Controller::class, 'runDetails']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:221`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/teaching/import116/runs/{import116_run}', [\App\Http\Controllers\Admin\Teaching\Import116Controller::class, 'destroyRun']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:229`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/config', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'config']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:230`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/subjects', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'storeSubject']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:231`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/subjects/{material_subject}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'updateSubject']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:232`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/subjects/{material_subject}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'destroySubject']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:233`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/subjects/{material_subject}/move', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'moveSubject']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:234`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/subjects/{material_subject}/convert-to-topic', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'convertSubjectToTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:235`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/topics', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'storeTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:236`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/topics/{material_topic}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'updateTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:237`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/topics/{material_topic}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'destroyTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:238`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/topics/{material_topic}/move', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'moveTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:239`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/topics/{material_topic}/unlink', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'unlinkTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:240`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/topics/{material_topic}/move-to-subject', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'moveTopicToSubject']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:241`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/topics/{material_topic}/convert-to-subject', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'convertTopicToSubject']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:242`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/topics/{material_topic}/convert-to-unit', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'convertTopicToUnit']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:243`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/units', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'storeUnit']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:244`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/units/{material_unit}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'updateUnit']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:245`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/units/{material_unit}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'destroyUnit']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:246`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/units/{material_unit}/move', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'moveUnit']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:247`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/units/{material_unit}/move-to-topic', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'moveUnitToTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:248`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/units/{material_unit}/convert-to-topic', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'convertUnitToTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:249`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/units/{material_unit}/unlink', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'unlinkUnit']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:250`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/user-settings', [\App\Http\Controllers\Admin\Materials\MaterialUserSettingsController::class, 'update']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:251`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/cards', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'index']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:252`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/cards', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'store']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:253`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/cards/quick_store', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'quickStore']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:254`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/cards/deleted-restore-list', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'deletedRestoreList']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:255`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/cards/last-deleted-restore-info', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'lastDeletedRestoreInfo']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:256`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/cards/restore-last-deleted', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'restoreLastDeleted']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:257`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/cards/restore-deleted/{card_id}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'restoreDeletedById'])->whereNumber('card_id');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:258`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/cards/deleted/{card_id}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'purgeDeletedById'])->whereNumber('card_id');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:259`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/cards/{material_card}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'show']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:260`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/cards/{material_card}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'update']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:261`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/cards/{material_card}/unlink', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'unlink']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:262`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/cards/{material_card}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'destroy']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:263`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/cards/{material_card}/attachments/link', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'storeLinkAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:264`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/cards/{material_card}/attachments/image-url', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'storeRemoteImageAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:265`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/cards/{material_card}/attachments/file', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'storeFileAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:266`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/cards/{material_card}/attachments/file-temp', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'storeTempFileAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:267`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/materials/attachments/{material_card_attachment}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'updateAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:268`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/attachments/{material_card_attachment}/text-content', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'textAttachmentContent']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:269`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/materials/attachments/{material_card_attachment}/text-content', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'updateTextAttachmentContent']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:270`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/attachments/{material_card_attachment}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'destroyAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:271`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/attachments/{material_card_attachment}/preview', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'previewAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:272`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/attachments/{material_card_attachment}/download', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'downloadAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:273`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/attachments/{material_card_attachment}/download-docx', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'downloadAttachmentDocx']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:274`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/shares', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'index']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:275`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/materials/shares/{material_share_rule}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'updateRule']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:276`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/targets', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeTarget']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:277`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/materials/shares/targets/{material_share_target}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'updateTarget']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:278`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/shares/targets/{material_share_target}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'destroyTarget']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:279`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/shares/lookup-users', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'lookupUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:280`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/shares/lookup-groups', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'lookupGroups']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:281`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/shares/lookup-schools', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'lookupSchools']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:282`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/shares/lookup-external-user', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'lookupExternalUser']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:283`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/shares/inbox-users', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'inboxUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:284`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/shares/inbox/material-attachments', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'inboxMaterialAttachments']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:285`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/shares/inbox/material-detail', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'inboxMaterialDetail']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:286`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/shares/inbox/material-detail', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'updateInboxMaterialDetail']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:287`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/shares/inbox/material-detail', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'destroyInboxMaterialDetail']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:288`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/subjects', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeInboxSubject']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:289`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/topics', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeInboxTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:290`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/units', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeInboxUnit']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:291`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/subjects/{material_subject}/move', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'moveInboxSubject']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:292`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/topics/{material_topic}/move', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'moveInboxTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:293`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/units/{material_unit}/move', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'moveInboxUnit']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:294`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/shares/inbox/subjects/{material_subject}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'updateInboxSubject']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:295`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/shares/inbox/topics/{material_topic}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'updateInboxTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:296`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/shares/inbox/units/{material_unit}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'updateInboxUnit']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:297`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/subjects/{material_subject}/materials', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeInboxSubjectMaterial']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:298`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/topics/{material_topic}/materials', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeInboxTopicMaterial']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:299`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/units/{material_unit}/materials', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeInboxUnitMaterial']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:300`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/shares/inbox/subjects/{material_subject}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'destroyInboxSubject']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:301`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/shares/inbox/topics/{material_topic}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'destroyInboxTopic']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:302`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/shares/inbox/units/{material_unit}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'destroyInboxUnit']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:303`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/material-attachments/link', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeInboxLinkAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:304`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/material-attachments/image-url', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeInboxRemoteImageAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:305`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/material-attachments/file', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeInboxFileAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:306`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/material-attachments/file-temp', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeInboxTempFileAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:307`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/materials/shares/inbox/material-attachments/{material_card_attachment}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'updateInboxAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:308`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/shares/inbox/material-attachments/{material_card_attachment}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'destroyInboxAttachment']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:309`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/shares/inbox/material-attachments/{material_card_attachment}/text-content', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'inboxTextAttachmentCont... (207 chars)
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:310`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/materials/shares/inbox/material-attachments/{material_card_attachment}/text-content', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'updateInboxTextAttach... (215 chars)
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:311`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/archive', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'archiveInboxRule']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:312`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/unarchive', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'unarchiveInboxRule']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:313`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/material-original-copy', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'copyInboxMaterialAsOriginal']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:314`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/shares/inbox/material-insert', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'insertInboxMaterial']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:315`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/uploads/chunk', [\App\Http\Controllers\Admin\Materials\MaterialChunkUploadController::class, 'upload']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:316`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/materials/uploads/chunk', [\App\Http\Controllers\Admin\Materials\MaterialChunkUploadController::class, 'uploadNext']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:317`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/uploads/chunk/{upload_id}', [\App\Http\Controllers\Admin\Materials\MaterialChunkUploadController::class, 'destroy']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:322`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/types', [\App\Http\Controllers\Admin\Materials\MaterialTypeController::class, 'index']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:323`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/types', [\App\Http\Controllers\Admin\Materials\MaterialTypeController::class, 'store']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:324`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/types/{material_type}', [\App\Http\Controllers\Admin\Materials\MaterialTypeController::class, 'update']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:325`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/types/{material_type}', [\App\Http\Controllers\Admin\Materials\MaterialTypeController::class, 'destroy']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:330`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/materials/statuses', [\App\Http\Controllers\Admin\Materials\MaterialStatusController::class, 'index']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:331`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/materials/statuses', [\App\Http\Controllers\Admin\Materials\MaterialStatusController::class, 'store']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:332`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/statuses/{material_status}', [\App\Http\Controllers\Admin\Materials\MaterialStatusController::class, 'update']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:333`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::delete('/admin/materials/statuses/{material_status}', [\App\Http\Controllers\Admin\Materials\MaterialStatusController::class, 'destroy']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:334`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/materials/file-settings', [\App\Http\Controllers\Admin\Materials\MaterialFileSettingsController::class, 'update']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:342`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/tutoring/delete_offers', [\App\Http\Controllers\Admin\Tutoring\OfferController::class, 'deleteOffers'])->middleware('tool-licensed:Nachhilfetool');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:343`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/tutoring/toggle_active_offer', [\App\Http\Controllers\Admin\Tutoring\OfferController::class, 'toggleActiveOffer'])->middleware('tool-licensed:Nachhilfetool');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:344`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/tutoring/toggle_accepted_offer', [\App\Http\Controllers\Admin\Tutoring\OfferController::class, 'toggleAcceptedOffer'])->middleware('tool-licensed:Nachhilfetool');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:345`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/tutoring/get_stats', [\App\Http\Controllers\Admin\Tutoring\OfferController::class, 'getStats'])->middleware('tool-licensed:Nachhilfetool');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:349`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/roles/load_roles', [\App\Http\Controllers\Admin\RoleController::class, 'loadRoles']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:353`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/licences/load_licences', [\App\Http\Controllers\Admin\LicenceController::class, 'loadLicences']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:354`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/licences/delete_licences', [\App\Http\Controllers\Admin\LicenceController::class, 'deleteLicences']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:355`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/licences/{licence}/save_licence_model', [\App\Http\Controllers\Admin\LicenceController::class, 'saveLicenceModel']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:359`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schools_upload/uploadLogo', [\App\Http\Controllers\Admin\SchoolController::class, 'uploadLogo']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:360`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::patch('/admin/schools_upload/uploadLogo', [\App\Http\Controllers\Admin\SchoolController::class, 'uploadLogoNext']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:361`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schools/delete_schools', [\App\Http\Controllers\Admin\SchoolController::class, 'deleteSchools']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:362`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schools/load_switchable_schools', [\App\Http\Controllers\Admin\SchoolController::class, 'loadSwitchableSchools']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:363`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schools/search_switch_users', [\App\Http\Controllers\Admin\SchoolController::class, 'searchSwitchUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:364`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schools/switch_school', [\App\Http\Controllers\Admin\SchoolController::class, 'switchSchool']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:365`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schools/load_school_infos', [\App\Http\Controllers\Admin\SchoolController::class, 'loadSchoolInfos']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:366`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schools/add_licence', [\App\Http\Controllers\Admin\SchoolController::class, 'addLicence']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:367`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schools/delete_licence', [\App\Http\Controllers\Admin\SchoolController::class, 'deleteLicence']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:368`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/school_licences/{school_licence}/save_licence_model', [\App\Http\Controllers\Admin\SchoolController::class, 'saveSchoolLicenceModel']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:369`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/school_licences/{school_licence}/users', [\App\Http\Controllers\Admin\SchoolController::class, 'loadSchoolLicenceUsers']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:370`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/school_licences/{school_licence}/users/{user}/roles', [\App\Http\Controllers\Admin\SchoolController::class, 'loadSchoolLicenceUserRoles']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:371`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/school_licences/{school_licence}/users/{user}/roles', [\App\Http\Controllers\Admin\SchoolController::class, 'saveSchoolLicenceUserRoles']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:372`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::put('/admin/school_licences/{school_licence}/users/{user}/spatie_roles', [\App\Http\Controllers\Admin\SchoolController::class, 'saveSchoolLicenceUserSpatieRoles']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:373`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/school_licences/{school_licence}/activate_user_licence', [\App\Http\Controllers\Admin\SchoolController::class, 'activateCurrentUserLicence']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:374`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/school_licences/{school_licence}/renew_user_licence', [\App\Http\Controllers\Admin\SchoolController::class, 'renewCurrentUserLicence']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:375`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/school_licences/{school_licence}/deactivate_user_licence', [\App\Http\Controllers\Admin\SchoolController::class, 'deactivateCurrentUserLicence']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:376`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schools/add_admin', [\App\Http\Controllers\Admin\SchoolController::class, 'addAdmin']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:377`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schools/delete_admin', [\App\Http\Controllers\Admin\SchoolController::class, 'deleteAdmin']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:380`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users/save_2fa', [UserController::class, 'save2Fa']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:381`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users/save_2fa_with_code', [UserController::class, 'save2FaWithCode']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:385`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/schoolyears/set_active', [\App\Http\Controllers\Admin\SchoolyearController::class, 'setActiveSchoolyear']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:386`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/schoolyears_paginate', [\App\Http\Controllers\Admin\SchoolyearController::class, 'indexPaginate']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:417`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users/destroy_multiple', [UserController::class, 'destroyMultiple']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:419`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users/confirm', [UserController::class, 'confirm']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:420`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users/save_user_roles', [UserController::class, 'saveUserRoles']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:424`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/roles/destroy_multiple', [SpaRoleController::class, 'destroyMultiple']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:425`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/load_roles', [\App\Http\Controllers\Admin\AdminController::class, 'loadRoles']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:428`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/users_with_roles/roles', [UserWithRoleController::class, 'roles']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:429`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/users_with_roles/roles', [UserWithRoleController::class, 'saveUserRoles']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:435`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/delete_log', [\App\Http\Controllers\Admin\LogController::class, 'deleteLog']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:436`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/restart_queues', [\App\Http\Controllers\Admin\LogController::class, 'restartQueues']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:437`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/impersonation/schools', [\App\Http\Controllers\Admin\ImpersonationController::class, 'schools']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:438`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/impersonation/users', [\App\Http\Controllers\Admin\ImpersonationController::class, 'users']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:439`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::post('/admin/impersonation/start', [\App\Http\Controllers\Admin\ImpersonationController::class, 'start']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:444`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/get_log', [\App\Http\Controllers\Admin\LogController::class, 'getLog']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\api.php:445`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/admin/list_logs', [\App\Http\Controllers\Admin\LogController::class, 'listLogs']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\web.php:77`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('tutoring_response', fn() => view('homepage'));
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\web.php:78`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('tutoring_overview', fn() => view('homepage'))->middleware('tool-licensed:Nachhilfetool');
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\web.php:84`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('confirm-user', [TutoringController::class, 'confirmUser']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\web.php:85`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('refuse-user', [TutoringController::class, 'refuseUser']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\web.php:86`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('offer', [\App\Http\Controllers\Tutoring\OfferController::class, 'offerConfirmRefuse']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\web.php:87`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('offer_request', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'offerRequest']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-001 — Route without middleware

- **File:** `routes\web.php:101`
- **Category:** Authentication
- **Scanner:** rules-scanner

A route is defined without any middleware. Depending on the route, this may expose endpoints without authentication or rate limiting. Sensitive routes should always have auth or throttle middleware.


```
Route::get('/homepage/{any?}',  [\App\Http\Controllers\Homepage\HomepageController::class, 'routing']);
```

**Remediation:**

Apply middleware to routes:
  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified']);
Or group routes:
  Route::middleware(['auth'])->group(function () { ... });


**References:**
- https://cwe.mitre.org/data/definitions/306.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `app\Providers\HorizonServiceProvider.php:28`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
return $request->user()?->hasRole('super_admin') || $request->user()?->hasRole('admin') ?? false;
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\SchoolControllerTest.php:1179`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
->and($user->hasRole('admin'))->toBeFalse();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\SchoolServiceTest.php:538`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeTrue();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\SchoolServiceTest.php:592`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeTrue();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\SchoolServiceTest.php:616`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeFalse();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\SchoolyearServiceTest.php:181`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
->and($user->hasRole('admin'))->toBeTrue();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\UserServiceTest.php:62`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
->and($user->hasRole('admin'))->toBeTrue()
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\UserServiceTest.php:123`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
->and($updatedUser->hasRole('admin'))->toBeTrue()
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\UserServiceTest.php:526`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeTrue();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\UserServiceTest.php:548`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeFalse();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\UserServiceTest.php:581`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user1->hasRole('admin'))->toBeTrue()
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\UserServiceTest.php:583`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
->and($user2->hasRole('admin'))->toBeTrue()
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\Services\UserServiceTest.php:604`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeFalse();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Feature\UserWithRoleControllerTest.php:106`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($target->fresh()->hasRole('admin'))->toBeTrue();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Unit\ModelsTest.php:836`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeTrue();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Unit\RegisterUserServiceTest.php:58`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
->and($user->hasRole('admin'))->toBeTrue();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Unit\SchoolServiceTest.php:561`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
->and($user->hasRole('admin'))->toBeTrue();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Unit\SchoolServiceTest.php:576`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeTrue()
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Unit\SchoolServiceTest.php:637`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeTrue()
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Unit\SchoolServiceTest.php:643`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeFalse()
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Unit\UserServiceTest.php:149`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeTrue()
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Unit\UserServiceTest.php:167`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($user->hasRole('admin'))->toBeTrue()
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Unit\UserServiceTest.php:226`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($updated->hasRole('admin'))->toBeTrue();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-002 — Hard-coded role or permission check

- **File:** `tests\Unit\UserServiceTest.php:243`
- **Category:** Authorization
- **Scanner:** rules-scanner

A role or permission is checked using a hardcoded string comparison. This is fragile, hard to maintain, and can lead to authorization bypasses when roles are renamed or restructured.


```
expect($updated->hasRole('admin'))->toBeFalse();
```

**Remediation:**

Use constants or enums for roles:
  if ($user->hasRole(Role::ADMIN)) { ... }
Better yet, use Laravel's Gate/Policy system:
  Gate::authorize('manage-users');


**References:**
- https://cwe.mitre.org/data/definitions/863.html

---

#### AUTH-004 — Sensitive action without password confirmation

- **File:** `app\Http\Controllers\Student\StudentController.php:193`
- **Category:** Authentication
- **Scanner:** rules-scanner

A sensitive operation (deleting account, changing email/password, managing payments) is performed without re-confirming the user's password. An attacker with a hijacked session could perform these actions.


```
public function changePassword(Request $request)
```

**Remediation:**

Use Laravel's password.confirm middleware:
  Route::delete('/account', [AccountController::class, 'destroy'])
      ->middleware(['auth', 'password.confirm']);


---

*Generated by [Ward](https://github.com/Eljakani/ward) v0.4.0*
