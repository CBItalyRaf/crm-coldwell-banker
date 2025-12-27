#!/usr/bin/env python3
"""
Import servizi agenzie da CSV a database
Mappa i campi servizi dal CSV Notion alla tabella agency_services
"""

import pandas as pd
import mysql.connector
from datetime import datetime

# Connessione database
db = mysql.connector.connect(
    host="localhost",
    user="crm_user",
    password="CRM_cb2025!Secure",
    database="crm_coldwell_banker"
)

cursor = db.cursor()

# Leggi CSV
csv_file = input("Inserisci il path del CSV agenzie: ")
df = pd.read_csv(csv_file)

print(f"Trovate {len(df)} agenzie nel CSV")
print("\nPrime 5 colonne:", list(df.columns[:5]))
print("\nCerco colonne servizi...")

# Trova colonne servizi (adatta questi nomi ai nomi REALI nel CSV)
service_columns = {
    'cb_suite': ['CB Suite', 'CB Suite dal', 'CB Suite al', 'Obbligo rinnovo', 'Fattura CB Suite'],
    'canva': ['CANVA'],
    'regold': ['ATTIVAZIONE REGOLD', 'SCADENZA REGOLD', 'Fattura Regold'],
    'james_edition': ['JamesEdition', 'SCADENZA JAMESEDITION'],
    'docudrop': ['ATTIVAZIONE DOCUDROP', 'SCADENZA DOCUDROP'],
    'unique': ['Attivazione Unique']
}

# Mostra colonne trovate
print("\nColonne nel CSV:")
for col in df.columns:
    if any(keyword.lower() in col.lower() for keyword in ['suite', 'canva', 'regold', 'james', 'docudrop', 'unique']):
        print(f"  - {col}")

print("\n" + "="*50)
confirm = input("Vuoi procedere con l'import? (si/no): ")
if confirm.lower() != 'si':
    print("Import annullato")
    exit()

# Funzione helper per convertire date
def parse_date(date_str):
    if pd.isna(date_str) or date_str == '' or date_str == 'Empty':
        return None
    try:
        return pd.to_datetime(date_str).strftime('%Y-%m-%d')
    except:
        return None

# Funzione helper per check attivo
def is_service_active(value):
    if pd.isna(value) or value == '' or value == 'Empty':
        return 0
    if str(value).lower() in ['attivo', 'active', 'si', 'yes', '1', 'true']:
        return 1
    return 0

# Import servizi
imported = 0
errors = 0

for idx, row in df.iterrows():
    agency_code = row.get('Codice', row.get('code', ''))
    
    if pd.isna(agency_code) or agency_code == '':
        continue
    
    # Trova agency_id
    cursor.execute("SELECT id FROM agencies WHERE code = %s", (agency_code,))
    result = cursor.fetchone()
    
    if not result:
        print(f"⚠️  Agenzia {agency_code} non trovata nel database")
        errors += 1
        continue
    
    agency_id = result[0]
    
    # CB Suite
    cb_suite_status = row.get('CB Suite (EuroMLS)', row.get('CB Suite', ''))
    if not pd.isna(cb_suite_status) and cb_suite_status != '' and cb_suite_status != 'Empty':
        cursor.execute("""
            INSERT INTO agency_services (agency_id, service_name, is_active, activation_date, expiration_date, renewal_required, invoice_reference, notes)
            VALUES (%s, 'cb_suite', %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE 
                is_active = VALUES(is_active),
                activation_date = VALUES(activation_date),
                expiration_date = VALUES(expiration_date),
                renewal_required = VALUES(renewal_required),
                invoice_reference = VALUES(invoice_reference),
                notes = VALUES(notes)
        """, (
            agency_id,
            is_service_active(cb_suite_status),
            parse_date(row.get('CB Suite dal', '')),
            parse_date(row.get('CB Suite al', '')),
            row.get('Obbligo rinnovo?', ''),
            row.get('Fattura CB Suite', ''),
            row.get('NOTE', '')
        ))
        imported += 1
    
    # Canva
    canva_status = row.get('CANVA', '')
    if not pd.isna(canva_status) and canva_status != '' and canva_status != 'Empty':
        cursor.execute("""
            INSERT INTO agency_services (agency_id, service_name, is_active)
            VALUES (%s, 'canva', %s)
            ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)
        """, (agency_id, is_service_active(canva_status)))
        imported += 1
    
    # Regold
    regold_activation = row.get('ATTIVAZIONE REGOLD', '')
    if not pd.isna(regold_activation) and regold_activation != '' and regold_activation != 'Empty':
        cursor.execute("""
            INSERT INTO agency_services (agency_id, service_name, is_active, activation_date, expiration_date, invoice_reference)
            VALUES (%s, 'regold', 1, %s, %s, %s)
            ON DUPLICATE KEY UPDATE 
                is_active = 1,
                activation_date = VALUES(activation_date),
                expiration_date = VALUES(expiration_date),
                invoice_reference = VALUES(invoice_reference)
        """, (
            agency_id,
            parse_date(regold_activation),
            parse_date(row.get('SCADENZA REGOLD', '')),
            row.get('Fattura Regold', '')
        ))
        imported += 1
    
    # James Edition
    james_status = row.get('JamesEdition', '')
    if not pd.isna(james_status) and james_status != '' and james_status != 'Empty':
        cursor.execute("""
            INSERT INTO agency_services (agency_id, service_name, is_active, expiration_date)
            VALUES (%s, 'james_edition', %s, %s)
            ON DUPLICATE KEY UPDATE 
                is_active = VALUES(is_active),
                expiration_date = VALUES(expiration_date)
        """, (
            agency_id,
            is_service_active(james_status),
            parse_date(row.get('SCADENZA JAMESEDITION', ''))
        ))
        imported += 1
    
    # Docudrop
    docudrop_activation = row.get('ATTIVAZIONE DOCUDROP', '')
    if not pd.isna(docudrop_activation) and docudrop_activation != '' and docudrop_activation != 'Empty':
        cursor.execute("""
            INSERT INTO agency_services (agency_id, service_name, is_active, activation_date, expiration_date)
            VALUES (%s, 'docudrop', 1, %s, %s)
            ON DUPLICATE KEY UPDATE 
                is_active = 1,
                activation_date = VALUES(activation_date),
                expiration_date = VALUES(expiration_date)
        """, (
            agency_id,
            parse_date(docudrop_activation),
            parse_date(row.get('SCADENZA DOCUDROP', ''))
        ))
        imported += 1
    
    # Unique
    unique_status = row.get('Attivazione Unique', '')
    if not pd.isna(unique_status) and unique_status != '' and unique_status != 'Empty':
        cursor.execute("""
            INSERT INTO agency_services (agency_id, service_name, is_active)
            VALUES (%s, 'unique', %s)
            ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)
        """, (agency_id, is_service_active(unique_status)))
        imported += 1

db.commit()

print(f"\n✅ Import completato!")
print(f"   Servizi importati: {imported}")
print(f"   Errori: {errors}")

cursor.close()
db.close()
