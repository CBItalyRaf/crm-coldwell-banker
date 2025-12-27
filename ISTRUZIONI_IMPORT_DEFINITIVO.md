# PROCEDURA IMPORT SERVIZI DEFINITIVA

## 🔄 OGNI VOLTA CHE DEVI IMPORTARE/AGGIORNARE SERVIZI:

### 1. EXPORT DA NOTION
- Apri database Agenzie in Notion
- Export → CSV
- Salva come `Agenzie_[data].csv` nella cartella GitHub

### 2. PULISCI IL CSV SUL SERVER
```bash
cd /var/www/admin.mycb.it

# Carica il CSV raw da Notion
scp /percorso/Agenzie_[data].csv root@46.224.153.65:/root/agenzie_raw.csv

# SSH sul server
ssh root@46.224.153.65

# Pulisci il CSV
cd /var/www/admin.mycb.it
python3 clean_notion_csv_DEFINITIVO.py

# Quando chiede:
# Input: /root/agenzie_raw.csv
# Output: /root/agenzie_clean.csv
```

### 3. IMPORTA SERVIZI
```bash
php import_services.php

# Quando chiede path:
# /root/agenzie_clean.csv
```

## ✅ FATTO!

---

## 📝 NOTE:

- **clean_notion_csv_DEFINITIVO.py** gestisce:
  - Link Notion → rimossi
  - Virgole nei campi → gestite
  - Tutte le colonne servizi → mappate
  - Tech fee, CAP, P.IVA → formattati

- **import_services.php** usa il CSV pulito senza problemi

- **NON modificare questi script** - funzionano sempre

---

## 🔧 FILE NECESSARI SUL SERVER:

- `/var/www/admin.mycb.it/clean_notion_csv_DEFINITIVO.py`
- `/var/www/admin.mycb.it/import_services.php`

---

## 🆘 SE QUALCOSA NON VA:

1. Verifica che il CSV Notion abbia le colonne giuste
2. Controlla output dello script clean (mostra colonne mancanti)
3. Se manca una colonna, aggiungi in COLUMNS_MAP dello script

---

**QUESTA È LA PROCEDURA. SEMPRE. FINE.** ✅
