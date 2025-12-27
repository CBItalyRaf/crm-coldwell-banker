#!/usr/bin/env python3
"""
Script DEFINITIVO per pulire CSV export Notion
Include TUTTE le colonne: agenzie + servizi
Gestisce link Notion, virgole nei campi, caratteri speciali
"""

import csv
import re
import sys

# TUTTE LE COLONNE DA ESTRARRE
COLUMNS_MAP = {
    # Info Base Agenzia
    'CBI': 'code',
    'Nome Agenzia': 'name',
    'Tipo agenzia': 'type',
    'Grandezza agenzia': 'agency_size',
    'Stato agenzia': 'status',
    'Broker Manager': 'broker_manager',
    'Cellulare Broker': 'broker_mobile',
    'Legale Rappresentante': 'legal_representative',
    
    # Indirizzi
    'Indirizzo Agenzia': 'address',
    'Indirizzo Legale': 'legal_address',
    'Comune': 'city',
    'Provincia': 'province',
    'CAP': 'zip_code',
    'Frazione': 'fraction',
    
    # Contatti
    'Email': 'email',
    'Telefono': 'phone',
    'PEC': 'pec',
    'Website': 'website',
    'Sito Agenzia': 'website_alt',
    
    # Dati Fiscali
    'Partita IVA': 'vat_number',
    'Codice fiscale Società': 'tax_code',
    'Ragione sociale': 'company_name',
    'REA': 'rea',
    'SDI': 'sdi_code',
    
    # Contrattuale
    'Sold Date': 'sold_date',
    'Data Apertura': 'activation_date',
    'Data chiusura': 'closed_date',
    'Durata contratto': 'contract_duration_years',
    'Scadenza': 'contract_expiry',
    'Rinnovi': 'renewals',
    'Tec fee da contratto €': 'tech_fee',
    'Note Fee Tec': 'tech_fee_notes',
    'Note contrattuali': 'contract_notes',
    
    # SERVIZI - CB Suite
    'CB Suite (EuroMq/iRealtors)': 'cb_suite_status',
    'CB Suite dal': 'cb_suite_start',
    'CB Suite al': 'cb_suite_end',
    'Obbligo rinnovo?': 'cb_suite_renewal',
    'Fattura CB Suite': 'cb_suite_invoice',
    'NOTE': 'cb_suite_notes',
    
    # SERVIZI - Canva
    'CANVA': 'canva_status',
    
    # SERVIZI - Regold
    'ATTIVAZIONE REGOLD': 'regold_activation',
    'SCADENZA REGOLD': 'regold_expiration',
    'Fattura Regold': 'regold_invoice',
    
    # SERVIZI - James Edition
    'JamesEdition': 'james_status',
    'SCADENZA JAMES EDITION': 'james_expiration',
    
    # SERVIZI - Docudrop
    'ATTIVAZIONE DOCUDROP': 'docudrop_activation',
    'SCADENZA DOCUDROP': 'docudrop_expiration',
    
    # SERVIZI - Unique
    'Attivazione Unique': 'unique_status',
}

def clean_notion_links(text):
    """Rimuove link Notion lasciando solo il testo pulito"""
    if not text or text == 'Empty':
        return ''
    
    # Pattern: CBI196 (https://www.notion.so/...)
    # Toglie tutto dopo lo spazio e la parentesi
    text = re.sub(r'\s*\(https://www\.notion\.so/[^)]+\)', '', text)
    
    # Se ci sono multipli link separati da virgola
    if ', ' in text:
        parts = [clean_notion_links(p.strip()) for p in text.split(', ')]
        return ', '.join(filter(None, parts))
    
    return text.strip()

def clean_value(value):
    """Pulisce un singolo valore"""
    if not value or value == 'Empty':
        return ''
    
    # Rimuovi link Notion
    value = clean_notion_links(value)
    
    # Rimuovi BOM UTF-8
    value = value.replace('\ufeff', '')
    
    # Rimuovi caratteri strani
    value = value.replace('\x00', '')
    
    # Strip whitespace
    value = value.strip()
    
    # Converti "Empty" in stringa vuota
    if value == 'Empty':
        return ''
    
    return value

def clean_tech_fee(value):
    """Pulisce tech fee: rimuove € e converte virgola in punto"""
    if not value or value == 'Empty':
        return ''
    
    # Rimuovi €, spazi, punti separatori migliaia
    value = value.replace('€', '').replace(' ', '').replace('.', '')
    
    # Converti virgola in punto per decimali
    value = value.replace(',', '.')
    
    try:
        float(value)
        return value
    except:
        return ''

def clean_zip_code(value):
    """Assicura che CAP sia sempre 5 cifre con zero padding"""
    if not value or value == 'Empty':
        return ''
    
    value = value.strip()
    
    # Se è numerico, aggiungi zero padding
    if value.isdigit():
        return value.zfill(5)
    
    return value

def clean_vat_number(value):
    """Pulisce Partita IVA: deve essere 11 cifre con zero padding"""
    if not value or value == 'Empty':
        return ''
    
    value = value.strip()
    
    # Se è numerico, aggiungi zero padding
    if value.isdigit():
        return value.zfill(11)
    
    return value

def process_csv(input_file, output_file):
    """Processa il CSV Notion e crea CSV pulito"""
    
    print(f"📖 Lettura: {input_file}")
    
    with open(input_file, 'r', encoding='utf-8-sig') as infile:
        reader = csv.DictReader(infile)
        
        # Verifica colonne presenti
        print(f"\n✅ Colonne trovate: {len(reader.fieldnames)}")
        
        missing = []
        for col in COLUMNS_MAP.keys():
            if col not in reader.fieldnames:
                missing.append(col)
        
        if missing:
            print(f"\n⚠️  Colonne mancanti nel CSV:")
            for m in missing:
                print(f"   - {m}")
        
        # Prepara output
        output_columns = list(COLUMNS_MAP.values())
        
        rows_processed = 0
        rows_written = 0
        
        with open(output_file, 'w', encoding='utf-8', newline='') as outfile:
            writer = csv.DictWriter(outfile, fieldnames=output_columns)
            writer.writeheader()
            
            for row in reader:
                rows_processed += 1
                
                clean_row = {}
                
                for notion_col, db_col in COLUMNS_MAP.items():
                    value = row.get(notion_col, '')
                    
                    # Pulizia specifica per tipo campo
                    if db_col == 'tech_fee':
                        clean_row[db_col] = clean_tech_fee(value)
                    elif db_col == 'zip_code':
                        clean_row[db_col] = clean_zip_code(value)
                    elif db_col == 'vat_number':
                        clean_row[db_col] = clean_vat_number(value)
                    else:
                        clean_row[db_col] = clean_value(value)
                
                writer.writerow(clean_row)
                rows_written += 1
        
        print(f"\n✅ COMPLETATO!")
        print(f"   Righe processate: {rows_processed}")
        print(f"   Righe scritte: {rows_written}")
        print(f"   Output: {output_file}")

if __name__ == '__main__':
    if len(sys.argv) == 3:
        input_file = sys.argv[1]
        output_file = sys.argv[2]
    else:
        print("=== PULIZIA CSV NOTION DEFINITIVA ===\n")
        input_file = input("File CSV Notion da pulire: ")
        output_file = input("File CSV output pulito: ")
    
    try:
        process_csv(input_file, output_file)
    except Exception as e:
        print(f"\n❌ ERRORE: {e}")
        sys.exit(1)
