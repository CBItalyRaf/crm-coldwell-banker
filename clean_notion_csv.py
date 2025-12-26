#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script di Pulizia CSV Notion per CRM Coldwell Banker
VERSIONE FINALE CORRETTA
- Agenzie: 33 campi
- Agenti: 14 campi (senza duplicati, relazionale puro)
"""

import pandas as pd
import re
import json
from datetime import datetime
import sys

# ============================================================================
# FUNZIONI DI VALIDAZIONE
# ============================================================================

def validate_email(email):
    if pd.isna(email) or email == '':
        return None
    email = str(email).strip()
    pattern = r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
    return email if re.match(pattern, email) else None

def validate_phone(phone):
    if pd.isna(phone) or phone == '':
        return None
    phone = str(phone).strip()
    phone = re.sub(r'[\s\-\(\)]', '', phone)
    if re.match(r'^\+?[0-9]{6,15}$', phone):
        return phone
    return None

def validate_vat(vat):
    if pd.isna(vat) or vat == '':
        return None
    vat = str(vat).strip().replace(' ', '')
    if '.' in vat:
        vat = vat.split('.')[0]
    if not vat.isdigit():
        return None
    if len(vat) < 11:
        vat = vat.zfill(11)
    if len(vat) == 11:
        return vat
    return None

def validate_cap(cap):
    if pd.isna(cap) or cap == '':
        return None
    cap = str(cap).strip().replace(' ', '')
    if '.' in cap:
        cap = cap.split('.')[0]
    if not cap.isdigit():
        return None
    if len(cap) < 5:
        cap = cap.zfill(5)
    if len(cap) == 5:
        return cap
    return None

def parse_date(date_str):
    if pd.isna(date_str) or date_str == '':
        return None
    try:
        for fmt in ['%Y-%m-%d', '%d/%m/%Y', '%d-%m-%Y', '%Y/%m/%d']:
            try:
                return datetime.strptime(str(date_str), fmt).strftime('%Y-%m-%d')
            except:
                continue
        return None
    except:
        return None

def clean_money(value):
    """Rimuove €, virgole, spazi da valori monetari"""
    if pd.isna(value) or value == '':
        return None
    value = str(value).strip()
    # Rimuovi €, spazi, virgole
    value = value.replace('€', '').replace(' ', '').replace(',', '')
    try:
        return float(value)
    except:
        return None

# ============================================================================
# PULIZIA AGENZIE
# ============================================================================

def clean_agenzie(df):
    print("\n=== PULIZIA AGENZIE ===")
    print(f"Righe originali: {len(df)}")
    
    df.columns = df.columns.str.strip().str.replace('\ufeff', '').str.replace('"', '')
    
    report = []
    
    # MAPPING 32 CAMPI
    column_mapping = {
        'CBI': 'code',
        'Nome Agenzia': 'name',
        'Stato agenzia': 'status',
        'Tipo agenzia': 'type',
        'Grandezza agenzia': 'agency_size',
        'Broker Manager': 'broker_manager',
        'Cellulare Broker': 'broker_mobile',
        'Legale Rappresentante': 'legal_representative',
        'Indirizzo Agenzia': 'address',
        'Indirizzo Legale': 'legal_address',
        'Comune': 'city',
        'Provincia': 'province',
        'CAP': 'zip_code',
        'Email': 'email',
        'Telefono': 'phone',
        'PEC': 'pec',
        'Website': 'website',
        'Partita IVA': 'vat_number',
        'Codice fiscale Società': 'tax_code',
        'Ragione sociale': 'company_name',
        'REA': 'rea',
        'SDI': 'sdi_code',
        'Sold Date': 'sold_date',
        'Data Apertura': 'activation_date',
        'Data chiusura': 'closed_date',
        'Durata contratto': 'contract_duration_years',
        'Scadenza': 'contract_expiry',
        'Rinnovi': 'renewals',
        'Tec fee da contratto €': 'tech_fee',
        'NOTE': 'notes',
        'Note Fee Tec': 'tech_fee_notes',
        'Note contrattuali': 'contract_notes',
    }
    
    df_clean = pd.DataFrame()
    
    for old_name, new_name in column_mapping.items():
        if old_name in df.columns:
            df_clean[new_name] = df[old_name]
        else:
            df_clean[new_name] = None
    
    print(f"Colonne estratte: {len(df_clean.columns)}/32")
    
    # Validazioni
    if 'email' in df_clean.columns:
        df_clean['email'] = df_clean['email'].apply(validate_email)
    
    if 'phone' in df_clean.columns:
        df_clean['phone'] = df_clean['phone'].apply(validate_phone)
    
    if 'vat_number' in df_clean.columns:
        df_clean['vat_number'] = df_clean['vat_number'].apply(validate_vat)
    
    if 'zip_code' in df_clean.columns:
        df_clean['zip_code'] = df_clean['zip_code'].apply(validate_cap)
    
    # FIX: Pulisci tech_fee (rimuove €, virgole)
    if 'tech_fee' in df_clean.columns:
        df_clean['tech_fee'] = df_clean['tech_fee'].apply(clean_money)
    
    for date_col in ['sold_date', 'activation_date', 'closed_date', 'contract_expiry']:
        if date_col in df_clean.columns:
            df_clean[date_col] = df_clean[date_col].apply(parse_date)
    
    if 'type' in df_clean.columns:
        type_mapping = {
            'Normale': 'Standard',
            'Standard': 'Standard',
            'Satellite': 'Satellite',
            'Master': 'Master',
            'Commercial': 'Commercial',
        }
        df_clean['type'] = df_clean['type'].map(type_mapping).fillna('Standard')
    
    if 'status' in df_clean.columns:
        status_mapping = {
            'Aperta': 'Active',
            'Chiusa': 'Closed',
            'Prospect': 'Prospect',
            'In apertura': 'Opening',
        }
        df_clean['status'] = df_clean['status'].map(status_mapping).fillna('Active')
    
    if 'agency_size' in df_clean.columns:
        size_mapping = {
            'Fino a 5': 'Small',
            'Da 5 a 10': 'Medium',
            'Oltre 10': 'Large',
        }
        df_clean['agency_size'] = df_clean['agency_size'].map(size_mapping)
    
    df_clean['data_incomplete'] = ~(
        df_clean.get('email', pd.Series()).notna() & 
        df_clean.get('phone', pd.Series()).notna() & 
        df_clean.get('vat_number', pd.Series()).notna()
    )
    
    report.append("\n=== REPORT AGENZIE ===")
    report.append(f"Totale agenzie: {len(df_clean)}")
    report.append(f"Colonne finali: {len(df_clean.columns)} (32 + data_incomplete)")
    
    if 'status' in df_clean.columns:
        report.append(f"\nStato agenzie:")
        for status, count in df_clean['status'].value_counts().items():
            report.append(f"  - {status}: {count}")
    
    print("\n".join(report))
    return df_clean, report

# ============================================================================
# PULIZIA AGENTI - SOLO 14 CAMPI
# ============================================================================

def clean_agenti(df):
    print("\n=== PULIZIA AGENTI ===")
    print(f"Righe originali: {len(df)}")
    
    df.columns = df.columns.str.strip().str.replace('\ufeff', '').str.replace('"', '')
    
    report = []
    
    # MAPPING SOLO 14 CAMPI (relazionale puro)
    column_mapping = {
        'ID_Agenzia': 'agency_code',
        'Nome': 'first_name',
        'Cognome': 'last_name',
        'Cellulare': 'mobile',
        'Mail Aziendale': 'email_corporate',
        'Email Personale': 'email_personal',
        'Piano M365 scelto': 'm365_plan',
        'Data attivazione Mail': 'email_activation_date',
        'Data Scadenza Mail': 'email_expiry_date',
        'Ruolo': 'role',
        'Stato': 'status',
        'data inserimento': 'inserted_at',
        'Note Mail': 'notes',
    }
    
    # Crea dataframe con SOLO colonne mappate
    df_clean = pd.DataFrame()
    
    for old_name, new_name in column_mapping.items():
        if old_name in df.columns:
            df_clean[new_name] = df[old_name]
        else:
            df_clean[new_name] = None
    
    print(f"Colonne estratte: {len(df_clean.columns)}/13")
    
    # Validazioni
    if 'email_corporate' in df_clean.columns:
        df_clean['email_corporate'] = df_clean['email_corporate'].apply(validate_email)
    
    if 'email_personal' in df_clean.columns:
        df_clean['email_personal'] = df_clean['email_personal'].apply(validate_email)
    
    if 'mobile' in df_clean.columns:
        df_clean['mobile'] = df_clean['mobile'].apply(validate_phone)
    
    for date_col in ['email_activation_date', 'email_expiry_date', 'inserted_at']:
        if date_col in df_clean.columns:
            df_clean[date_col] = df_clean[date_col].apply(parse_date)
    
    if 'status' in df_clean.columns:
        status_mapping = {
            'Attivo': 'Active',
            'Disattivo': 'Inactive',
            'Inattivo': 'Inactive',
        }
        df_clean['status'] = df_clean['status'].map(status_mapping).fillna('Active')
    
    # Normalizza role (solo il primo se multipli)
    if 'role' in df_clean.columns:
        def normalize_role(role_str):
            if pd.isna(role_str) or role_str == '':
                return 'Agent'
            roles = re.split(r'[,;]', str(role_str))
            first_role = roles[0].strip() if roles else 'Agent'
            # Normalizza
            if 'Broker' in first_role or 'broker' in first_role.lower():
                return 'Broker'
            if 'Preposto' in first_role or 'Manager' in first_role:
                return 'Manager'
            if 'Legale' in first_role:
                return 'Legal Representative'
            return first_role if first_role else 'Agent'
        
        df_clean['role'] = df_clean['role'].apply(normalize_role)
    
    # Flag data_incomplete
    df_clean['data_incomplete'] = ~(
        df_clean.get('last_name', pd.Series()).notna() & 
        (df_clean.get('email_corporate', pd.Series()).notna() | 
         df_clean.get('email_personal', pd.Series()).notna()) &
        df_clean.get('mobile', pd.Series()).notna()
    )
    
    report.append("\n=== REPORT AGENTI ===")
    report.append(f"Totale agenti: {len(df_clean)}")
    report.append(f"Colonne finali: {len(df_clean.columns)} (13 + data_incomplete)")
    
    if 'status' in df_clean.columns:
        report.append(f"\nStato agenti:")
        for status, count in df_clean['status'].value_counts().items():
            report.append(f"  - {status}: {count}")
    
    if 'role' in df_clean.columns:
        report.append(f"\nRuoli:")
        for role, count in df_clean['role'].value_counts().head(10).items():
            report.append(f"  - {role}: {count}")
    
    incomplete = df_clean['data_incomplete'].sum()
    report.append(f"\nDati incompleti: {incomplete}")
    
    print("\n".join(report))
    return df_clean, report

# ============================================================================
# MAIN
# ============================================================================

def main():
    print("=" * 80)
    print("SCRIPT PULIZIA CSV - CRM COLDWELL BANKER")
    print("Agenzie: 33 campi | Agenti: 14 campi (relazionale)")
    print("=" * 80)
    
    try:
        df_agenzie = pd.read_csv(
            'Agenzie_dic_2025.csv', 
            dtype={'Partita IVA': str, 'CAP': str}, 
            encoding='utf-8-sig', 
            keep_default_na=False
        )
        df_agenzie = df_agenzie.replace('', pd.NA)
        
        df_agenti = pd.read_csv('Agenti_2025.csv', encoding='utf-8-sig')
        
        df_agenzie_clean, report_agenzie = clean_agenzie(df_agenzie)
        df_agenti_clean, report_agenti = clean_agenti(df_agenti)
        
        print("\n=== SALVATAGGIO ===")
        df_agenzie_clean.to_csv('agenzie_clean.csv', index=False)
        print(f"✅ agenzie_clean.csv ({len(df_agenzie_clean.columns)} colonne)")
        
        df_agenti_clean.to_csv('agenti_clean.csv', index=False)
        print(f"✅ agenti_clean.csv ({len(df_agenti_clean.columns)} colonne)")
        
        with open('report_pulizia.txt', 'w', encoding='utf-8') as f:
            f.write('\n'.join(report_agenzie + report_agenti))
        print(f"✅ report_pulizia.txt")
        
        print("\n" + "=" * 80)
        print("✅ PULIZIA COMPLETATA!")
        print("=" * 80)
        
    except Exception as e:
        print(f"❌ ERRORE: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)

if __name__ == '__main__':
    main()
