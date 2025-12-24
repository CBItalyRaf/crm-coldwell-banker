# CRM Coldwell Banker Italy

Sistema CRM per gestione network affiliati Coldwell Banker Italia.

## 🚀 Installazione Database

### Requisiti
- MySQL 8.0+
- PHP 8.3+
- Accesso root MySQL

### Step 1: Crea Database e Utente

Collegati a MySQL come root:

```bash
mysql -u root -p
```

Esegui questi comandi:

```sql
CREATE DATABASE IF NOT EXISTS crm_coldwell_banker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'crm_user'@'localhost' IDENTIFIED BY 'CRM_cb2025!Secure';
GRANT ALL PRIVILEGES ON crm_coldwell_banker.* TO 'crm_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 2: Importa Schema

```bash
cd /var/www/admin.mycb.it
php install_database.php
```

Oppure manualmente:

```bash
mysql -u crm_user -p crm_coldwell_banker < sql/schema.sql
```

## 📊 Struttura Database

### Tabelle

- `agencies` - Agenzie (105 attive + 47 chiuse + 5 prospect)
- `agents` - Agenti (1364 totali, 760 attivi)
- `services` - Catalogo servizi (CB Suite, Canva, etc)
- `agency_services` - Sottoscrizioni servizi per agenzia
- `agent_transfers` - Storico trasferimenti agenti tra agenzie

### Relazioni

```
agencies (1) ----< (N) agents
agencies (1) ----< (N) agency_services >---- (1) services
agents (1) ----< (N) agent_transfers
```

## 🔧 Configurazione

File di configurazione: `config/database.php`

Credenziali default:
- Host: localhost
- Database: crm_coldwell_banker
- User: crm_user
- Password: CRM_cb2025!Secure

**⚠️ Cambia la password in produzione!**

## 📋 Prossimi Step

1. ✅ Database creato
2. ⏳ Script pulizia CSV Notion
3. ⏳ Script import dati
4. ⏳ Dashboard home
5. ⏳ Modulo Agenzie
6. ⏳ Modulo Agenti

## 🔐 Sicurezza

- Mai committare `config/database.php` con credenziali reali
- Usa variabili d'ambiente in produzione
- Backup regolari database

## 📞 Supporto

Raf - raffaella.pace@cbitaly.it
