# ISTRUZIONI IMPORT DATI CRM

## 📦 File Creati:

1. **clean_notion_csv.py** - Script pulizia CSV (CORRETTO)
2. **import_csv_to_db.php** - Script import database (CORRETTO)
3. **fix_agents_table.sql** - Comandi SQL pulizia tabella agents

---

## 🚀 PROCEDURA COMPLETA:

### STEP 1: Pulisci CSV sul Mac

**Sul tuo Mac:**

```bash
cd /Users/raffaella/GitHub/crm-coldwell-banker
python3 clean_notion_csv.py
```

**Output:**
- `agenzie_clean.csv` (33 colonne)
- `agenti_clean.csv` (14 colonne)
- `report_pulizia.txt`

---

### STEP 2: Carica File sul Server

```bash
scp import_csv_to_db.php root@46.224.153.65:/var/www/admin.mycb.it/
scp agenzie_clean.csv root@46.224.153.65:/var/www/admin.mycb.it/
scp agenti_clean.csv root@46.224.153.65:/var/www/admin.mycb.it/
```

---

### STEP 3: Pulisci Tabella Agents

**SSH nel server:**

```bash
ssh root@46.224.153.65
mysql
```

**In MySQL:**

```sql
USE crm_coldwell_banker;

ALTER TABLE agents
DROP COLUMN IF EXISTS is_broker,
DROP COLUMN IF EXISTS is_legal_rep,
DROP COLUMN IF EXISTS is_manager,
DROP COLUMN IF EXISTS tags;

EXIT;
```

---

### STEP 4: Esegui Import

```bash
cd /var/www/admin.mycb.it
php import_csv_to_db.php
```

---

## ✅ RISULTATO ATTESO:

```
=== IMPORT AGENZIE ===
✅ Inserite:   162
❌ Errori:     0

=== IMPORT AGENTI ===
✅ Inseriti:   1195
⚠️  Saltati:    169 (senza cognome)
❌ Errori:     0

✅ IMPORT COMPLETATO CON SUCCESSO!
```

---

## 📋 STRUTTURA FINALE:

### AGENZIE (33 campi):
- code, name, type, status, agency_size
- broker_manager, broker_mobile, legal_representative
- address, legal_address, city, province, zip_code
- email, phone, pec, website
- vat_number, tax_code, company_name, rea, sdi_code
- sold_date, activation_date, closed_date, contract_duration_years, contract_expiry, renewals, tech_fee
- notes, tech_fee_notes, contract_notes
- data_incomplete

### AGENTI (14 campi):
- agency_code (FK)
- first_name, last_name, mobile
- email_corporate, email_personal
- m365_plan, email_activation_date, email_expiry_date
- role, status, inserted_at
- notes, data_incomplete

---

## 🔧 FIX APPLICATI:

1. ✅ Script Python: pulisce tech_fee (rimuove € e virgole)
2. ✅ Script Python: seleziona SOLO 14 campi agenti (relazionale puro)
3. ✅ Script PHP: usa nomi corretti colonne
4. ✅ Database: rimosse colonne duplicate (is_broker, is_legal_rep, is_manager, tags)

---

## 📞 Note:

- tech_fee ora è DECIMAL pulito (no €, no virgole)
- role è stringa singola normalizzata (Broker, Agent, Manager, Legal Representative)
- Dati duplicati rimossi (Nome Agenzia, Località, etc in agenti)
- Database completamente relazionale
