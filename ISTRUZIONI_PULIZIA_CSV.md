# Script Pulizia CSV Notion

## 📋 Cosa Fa

Questo script Python pulisce i CSV esportati da Notion per prepararli all'import nel CRM.

### Operazioni Eseguite:

**Agenzie:**
- ✅ Rimuove colonne inutili (Rollup, link Notion, formule)
- ✅ Rinomina colonne in inglese (per database)
- ✅ Valida email, telefono, P.IVA
- ✅ Normalizza date (formato ISO)
- ✅ Normalizza tipo/stato agenzia
- ✅ Flag `data_incomplete` per dati mancanti

**Agenti:**
- ✅ Rimuove colonne duplicate
- ✅ **MANTIENE email personale** (importante!)
- ✅ Valida email aziendale, email personale, cellulare
- ✅ Split ruoli multipli (es: "Broker, Legale Rappresentante")
- ✅ Estrae flag boolean (is_broker, is_legal_rep, is_manager)
- ✅ Crea campo `tags` JSON per ruoli extra
- ✅ Normalizza stato (Attivo → Active, Disattivo → Inactive)

## 🚀 Come Usare

### 1. Prepara i File

Metti i CSV esportati da Notion nella stessa cartella dello script:
```
/Users/raffaella/GitHub/crm-coldwell-banker/
├── clean_notion_csv.py
├── Agenzie_dic_2025.csv  ← I tuoi CSV
├── Agenti_2025.csv       ← I tuoi CSV
```

### 2. Installa Dipendenze

```bash
pip3 install pandas --break-system-packages
```

(Solo la prima volta!)

### 3. Esegui lo Script

```bash
cd /Users/raffaella/GitHub/crm-coldwell-banker
python3 clean_notion_csv.py
```

### 4. Ottieni Output

Lo script genera 3 file:

- **agenzie_clean.csv** - Agenzie pulite pronte per import
- **agenti_clean.csv** - Agenti puliti pronti per import
- **report_pulizia.txt** - Report dettagliato anomalie

## 📊 Report Esempio

```
=== REPORT AGENZIE ===
Totale agenzie: 162
Colonne finali: 25

Stato agenzie:
  - Active: 105
  - Closed: 47
  - Prospect: 5
  - Opening: 5

Anomalie rilevate:
  - senza_email: 18
  - senza_telefono: 51
  - senza_piva: 28

Agenzie con dati incompleti: 63

=== REPORT AGENTI ===
Totale agenti: 1364
Colonne finali: 20

Stato agenti:
  - Active: 760
  - Inactive: 568

Anomalie rilevate:
  - senza_cognome: 169
  - senza_email_corporate: 386
  - senza_cellulare: 372

Agenti con dati incompleti: 489
Broker: 125
Legali Rappresentanti: 98
Manager/Preposti: 45
```

## 🔍 Validazioni Applicate

### Email
- Formato: `nome@dominio.ext`
- Elimina email malformate
- Trim spazi

### Telefono
- Rimuove spazi, trattini, parentesi
- Accetta solo numeri + eventuale `+`
- Lunghezza 6-15 caratteri

### Partita IVA
- Esattamente 11 cifre
- Solo numeri

### Date
- Formati accettati: `YYYY-MM-DD`, `DD/MM/YYYY`, `DD-MM-YYYY`
- Output: ISO `YYYY-MM-DD`

## ⚠️ Note Importanti

1. **Email Personale Mantenuta**: Fondamentale come contatto alternativo
2. **Dati Incompleti Importati**: Flag `data_incomplete=true` ma record presente
3. **Ruoli Multipli**: Splittati e normalizzati (broker flag + tags JSON)
4. **Storico Preservato**: Chiuse/Inattivi importati per storico

## 🔄 Aggiornamenti Periodici

Durante sviluppo CRM:
- Esporta CSV da Notion
- Esegui script
- Carica CSV puliti nel CRM (via interfaccia web)

## 🐛 Troubleshooting

**Errore "File not found":**
```bash
# Verifica di essere nella cartella giusta
pwd
# Dovresti vedere: /Users/raffaella/GitHub/crm-coldwell-banker

# Lista file
ls *.csv
# Dovresti vedere: Agenzie_dic_2025.csv, Agenti_2025.csv
```

**Errore "pandas not found":**
```bash
pip3 install pandas --break-system-packages
```

**Encoding errors:**
- Assicurati che i CSV siano UTF-8
- In Excel: Salva come → CSV UTF-8

## 📞 Supporto

Per problemi o domande: raffaella.pace@cbitaly.it
