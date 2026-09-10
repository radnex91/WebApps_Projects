#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Reconstruit data/import_articles_hopitaux_cliniques_cm.csv a partir du VRAI
listing Laborex Garoua (LISTING LABOREX GAROUA 12082025.xlsx).
Colonnes source : NOART, LIBART, ABRLABO, CODCIP, QTE DISPO, CESSIO, PUBLIC
  - reference   = CODCIP (CIP reel) sinon NOART
  - nom         = LIBART
  - fournisseur = Laborex + code labo (ABRLABO)
  - stock       = QTE DISPO (reel listing)
  - prix_achat  = CESSIO  (prix de cession distributeur)
  - prix_vente  = PUBLIC  (prix public regule) ; si PUBLIC=0 -> CESSIO*1,19
  - tva         = 0 (medicaments) / 19.25 (dispositifs, reactifs, consommables)
  - date_expiration / seuil : laisses vides / defaut (non fournis par le listing)
Usage : python tools/build_import_from_laborex.py
"""
import openpyxl, csv, re, sys, os

SRC = "LISTING LABOREX GAROUA 12082025.xlsx"
OUT = "data/import_articles_hopitaux_cliniques_cm.csv"

# (mot-cle dans LIBART, categorie, tva). Ordre = priorite.
# On cible les denominations communes (generiques) des essentiels hospitaliers CM.
RULES = [
    # --- Antalgiques / antipyretiques ---
    ("PARACETAMOL", "Antalgiques", 0),
    ("ACETAMINOPH", "Antalgiques", 0),
    ("ACIDO ACETILSALICILIC", "Antalgiques", 0),
    ("ASPIRIN", "Antalgiques", 0),
    ("IBUPROF", "Antalgiques", 0),
    ("ACIDE MEFENAM", "Antalgiques", 0),
    ("MEFENAM", "Antalgiques", 0),
    ("DICLOFENAC", "Antalgiques", 0),
    ("ACECLOFENAC", "Antalgiques", 0),
    ("KETOPROF", "Antalgiques", 0),
    ("NIFLUMI", "Antalgiques", 0),
    # --- Antibiotiques ---
    ("AMOXICILL", "Antibiotiques", 0),
    ("AMPICILL", "Antibiotiques", 0),
    ("CLOXACILL", "Antibiotiques", 0),
    ("PHENOXYMETHYLPENIC", "Antibiotiques", 0),
    ("PENICILLINE V", "Antibiotiques", 0),
    ("COTRIMOXAZ", "Antibiotiques", 0),
    ("TRIMETHOPRIM", "Antibiotiques", 0),
    ("METRONIDAZ", "Antibiotiques", 0),
    ("CIPROFLOXA", "Antibiotiques", 0),
    ("NORFLOXA", "Antibiotiques", 0),
    ("OFLOXA", "Antibiotiques", 0),
    ("CEFALEX", "Antibiotiques", 0),
    ("CEFADROX", "Antibiotiques", 0),
    ("CEFTRIAX", "Antibiotiques", 0),
    ("CEFTAZID", "Antibiotiques", 0),
    ("CEFOTAX", "Antibiotiques", 0),
    ("CEFUROX", "Antibiotiques", 0),
    ("ERYTHROMY", "Antibiotiques", 0),
    ("AZITHROMY", "Antibiotiques", 0),
    ("CLARITHROMY", "Antibiotiques", 0),
    ("DOXYCYCL", "Antibiotiques", 0),
    ("TETRACYCL", "Antibiotiques", 0),
    ("NITROFURAN", "Antibiotiques", 0),
    ("GENTAMIC", "Antibiotiques", 0),
    ("CHLORAMPH", "Antibiotiques", 0),
    ("CLINDAMY", "Antibiotiques", 0),
    ("SPECTINOMY", "Antibiotiques", 0),
    # --- Antipaludiques ---
    ("ARTEMETHER", "Antipaludiques", 0),
    ("LUMEFANTR", "Antipaludiques", 0),
    ("ARTESUNATE", "Antipaludiques", 0),
    ("AMODIAQU", "Antipaludiques", 0),
    ("SULFADOX", "Antipaludiques", 0),
    ("PYRIMETH", "Antipaludiques", 0),
    ("QUININE", "Antipaludiques", 0),
    ("CHLOROQU", "Antipaludiques", 0),
    ("PRIMAQU", "Antipaludiques", 0),
    ("MEFLOQU", "Antipaludiques", 0),
    ("HALOFANTR", "Antipaludiques", 0),
    # --- Cardiovasculaire / antihypertenseurs ---
    ("CAPTOPRIL", "Cardiovasculaire", 0),
    ("ENALAPRIL", "Cardiovasculaire", 0),
    ("LISINOPRIL", "Cardiovasculaire", 0),
    ("HYDROCHLOROTH", "Cardiovasculaire", 0),
    ("ATENOLOL", "Cardiovasculaire", 0),
    ("PROPRANOLOL", "Cardiovasculaire", 0),
    ("AMLODIP", "Cardiovasculaire", 0),
    ("NIFEDIP", "Cardiovasculaire", 0),
    ("METHYLDOPA", "Cardiovasculaire", 0),
    ("FUROSEM", "Cardiovasculaire", 0),
    ("SPIRONOLAC", "Cardiovasculaire", 0),
    ("DIGOXIN", "Cardiovasculaire", 0),
    ("ACENOCOUMAR", "Cardiovasculaire", 0),
    ("WARFARIN", "Cardiovasculaire", 0),
    ("HEPARINE", "Cardiovasculaire", 0),
    # --- Antidiabetiques ---
    ("METFORMIN", "Antidiabetiques", 0),
    ("GLIBENCLAM", "Antidiabetiques", 0),
    ("GLICLAZ", "Antidiabetiques", 0),
    ("GLIMEPIR", "Antidiabetiques", 0),
    ("INSULIN", "Antidiabetiques", 0),
    ("GLUCONATE", "Antidiabetiques", 0),
    # --- Gastro-enterologie ---
    ("OMEPRAZ", "Gastro-enterologie", 0),
    ("PANTOPRAZ", "Gastro-enterologie", 0),
    ("RANITID", "Gastro-enterologie", 0),
    ("FAMOTID", "Gastro-enterologie", 0),
    ("ALUMINI", "Gastro-enterologie", 0),
    ("METOCLOPRA", "Gastro-enterologie", 0),
    ("LOPERAM", "Gastro-enterologie", 0),
    ("DOMPERID", "Gastro-enterologie", 0),
    ("TIORFAN", "Gastro-enterologie", 0),
    ("REHYDRAT", "Gastro-enterologie", 0),
    ("SRO", "Gastro-enterologie", 0),
    # --- Respiratoire / antihistaminiques ---
    ("SALBUTAM", "Respiratoire", 0),
    ("THEOPHYL", "Respiratoire", 0),
    ("SALMET", "Respiratoire", 0),
    ("BECLOMET", "Respiratoire", 0),
    ("BUDESON", "Respiratoire", 0),
    ("CHLORPHEN", "Antihistaminiques", 0),
    ("PROMETH", "Antihistaminiques", 0),
    ("LORATAD", "Antihistaminiques", 0),
    ("CETIRIZ", "Antihistaminiques", 0),
    # --- Vitamines / mineraux / fer ---
    ("ACIDE ASCORB", "Vitamines et mineraux", 0),
    ("ASCORB", "Vitamines et mineraux", 0),
    ("POLYVIT", "Vitamines et mineraux", 0),
    ("ACIDE FOL", "Vitamines et mineraux", 0),
    ("FOLIQUE", "Vitamines et mineraux", 0),
    ("SULFATE DE FER", "Vitamines et mineraux", 0),
    ("FER ", "Vitamines et mineraux", 0),
    ("ZINC", "Vitamines et mineraux", 0),
    ("VITAMINE A", "Vitamines et mineraux", 0),
    ("RETINOL", "Vitamines et mineraux", 0),
    ("CYANOCOBAL", "Vitamines et mineraux", 0),
    ("THIAM", "Vitamines et mineraux", 0),
    # --- Anthelminthiques ---
    ("MEBENDAZ", "Anthelminthiques", 0),
    ("ALBENDAZ", "Anthelminthiques", 0),
    ("PRAZIQUAN", "Anthelminthiques", 0),
    ("IVERMECT", "Anthelminthiques", 0),
    ("LEVAMIS", "Anthelminthiques", 0),
    ("NICOLOSAM", "Anthelminthiques", 0),
    # --- Antifongiques ---
    ("NYSTAT", "Antifongiques", 0),
    ("GRISEOFULV", "Antifongiques", 0),
    ("CLOTRIMAZ", "Antifongiques", 0),
    ("FLUCONAZ", "Antifongiques", 0),
    ("KETOCONAZ", "Antifongiques", 0),
    ("MICONAZ", "Antifongiques", 0),
    ("TERBINAF", "Antifongiques", 0),
    # --- Antituberculeux / antilepre ---
    ("ISONIAZ", "Antituberculeux", 0),
    ("RIFAMP", "Antituberculeux", 0),
    ("PYRAZINAM", "Antituberculeux", 0),
    ("ETHAMBUT", "Antituberculeux", 0),
    ("ETHIONAM", "Antituberculeux", 0),
    ("DAPSON", "Antituberculeux", 0),
    ("CLOFAZIM", "Antituberculeux", 0),
    # --- Neurologie / anticonvulsivants ---
    ("PHENOBAR", "Neurologie", 0),
    ("PHENYTO", "Neurologie", 0),
    ("DIAZEPAM", "Neurologie", 0),
    ("CARBAMAZ", "Neurologie", 0),
    ("VALPRO", "Neurologie", 0),
    ("LEVETIRACET", "Neurologie", 0),
    # --- Corticoides ---
    ("DEXAMETH", "Corticoides", 0),
    ("HYDROCORT", "Corticoides", 0),
    ("PREDNISOL", "Corticoides", 0),
    ("PREDNIS", "Corticoides", 0),
    ("BETAMETH", "Corticoides", 0),
    # --- Gyneco-obstetrique ---
    ("OXYTOC", "Gyneco-obstetrique", 0),
    ("TRANEXAM", "Gyneco-obstetrique", 0),
    ("MAGNESIUM SULF", "Gyneco-obstetrique", 0),
    ("MISOPROST", "Gyneco-obstetrique", 0),
    ("ERGOMETR", "Gyneco-obstetrique", 0),
    # --- Antiseptiques / dermatologie ---
    ("POVIDONE", "Dermatologie et antiseptiques", 0),
    ("BETADINE", "Dermatologie et antiseptiques", 0),
    ("ALCOOL", "Dermatologie et antiseptiques", 0),
    ("DAKIN", "Dermatologie et antiseptiques", 0),
    ("HYPOCHLOR", "Dermatologie et antiseptiques", 0),
    ("PERMANGANATE", "Dermatologie et antiseptiques", 0),
    ("VASELINE", "Dermatologie et antiseptiques", 0),
    ("SULFADIAZ", "Dermatologie et antiseptiques", 0),
    # --- Ophtalmologie ---
    ("TETRACYCL POMM OPHT", "Ophtalmologie", 0),
    ("NITRATE D ARGENT", "Ophtalmologie", 0),
    ("COLLYRE", "Ophtalmologie", 0),
    # --- Perfusions / solutes ---
    ("CHLORURE DE SODIUM", "Perfusion", 0),
    ("SERUM PHYSIOLOG", "Perfusion", 0),
    ("GLUCOSE 5", "Perfusion", 0),
    ("GLUCOSE 10", "Perfusion", 0),
    ("GLUCOSE 30", "Perfusion", 0),
    ("GLUCOSE ", "Perfusion", 0),
    ("RINGER", "Perfusion", 0),
    ("BICARBONATE DE SODIUM", "Perfusion", 0),
    ("EAU POUR PREP INJ", "Perfusion", 0),
    ("EAU PPI", "Perfusion", 0),
    ("POLYGELINE", "Perfusion", 0),
    ("HYDROXYETHYL", "Perfusion", 0),
    # --- Anesthesie / urgences / reanimation ---
    ("LIDOCA", "Anesthesie", 0),
    ("KETAMIN", "Anesthesie", 0),
    ("PROPOFOL", "Anesthesie", 0),
    ("ADRENAL", "Urgences et reanimation", 0),
    ("ATROPIN", "Urgences et reanimation", 0),
    ("NALOXON", "Urgences et reanimation", 0),
    ("DOPAMIN", "Urgences et reanimation", 0),
    ("NALPHINE", "Urgences et reanimation", 0),
    # --- Reactifs / TDR / laboratoire (TVA 19.25) ---
    ("TDR", "Laboratoire et reactifs", 19.25),
    ("TEST DIAGNOST", "Laboratoire et reactifs", 19.25),
    ("BANDELETTE", "Laboratoire et reactifs", 19.25),
    ("GLYCEMIE", "Laboratoire et reactifs", 19.25),
    ("ACCU CHEK", "Laboratoire et reactifs", 19.25),
    ("GLUCOMETRE", "Laboratoire et reactifs", 19.25),
    ("HEMOGLOB", "Laboratoire et reactifs", 19.25),
    # --- Consommables / dispositifs (TVA 19.25) ---
    ("SERINGUE", "Consommables medicaux", 19.25),
    ("AIGUILLE", "Consommables medicaux", 19.25),
    ("CATHETER", "Consommables medicaux", 19.25),
    ("CATHET", "Consommables medicaux", 19.25),
    ("SET DE PERFUS", "Consommables medicaux", 19.25),
    ("PERFUSEUR", "Consommables medicaux", 19.25),
    ("GANT", "Consommables medicaux", 19.25),
    ("COMPRESS", "Consommables medicaux", 19.25),
    ("COTON", "Consommables medicaux", 19.25),
    ("SPARADRAP", "Consommables medicaux", 19.25),
    ("BANDE DE GAZE", "Consommables medicaux", 19.25),
    ("MASQUE", "Consommables medicaux", 19.25),
    ("ALÈSE", "Consommables medicaux", 19.25),
    ("ALESE", "Consommables medicaux", 19.25),
    ("HYDRO ALCOOL", "Consommables medicaux", 19.25),
    ("SUTURE", "Consommables medicaux", 19.25),
    ("TENSIO", "Equipement medical", 19.25),
    ("STETHOSCOPE", "Equipement medical", 19.25),
    ("THERMOMETRE", "Equipement medical", 19.25),
]

def pick_category(lib):
    L = lib.upper()
    for kw, cat, tva in RULES:
        if kw in L:
            return cat, tva
    return None, None

def main():
    wb = openpyxl.load_workbook(SRC, data_only=True, read_only=True)
    ws = wb["Feuil1"]
    out_rows = []
    seen = set()
    n_total = 0
    for r in ws.iter_rows(min_row=2, values_only=True):
        if not r or r[1] is None:
            continue
        noart = str(r[0]).strip() if r[0] is not None else ""
        lib   = str(r[1]).strip()
        labo  = str(r[2]).strip() if r[2] is not None else ""
        cip   = str(r[3]).strip() if r[3] is not None else ""
        try:
            qte  = int(float(r[4] or 0))
        except Exception:
            qte  = 0
        try:
            cess = float(r[5] or 0)
        except Exception:
            cess = 0.0
        try:
            pub  = float(r[6] or 0)
        except Exception:
            pub  = 0.0
        if cess <= 0:
            continue
        cat, tva = pick_category(lib)
        if cat is None:
            continue
        n_total += 1
        ref = cip if cip and cip != "0" else noart
        if ref in seen:
            continue
        seen.add(ref)
        pv = pub if pub > 0 else round(cess * 1.19)
        fourn = f"Laborex ({labo})" if labo else "Laborex"
        # seuil indicatif selon categorie
        seuil = 10 if tva == 0 else 5
        out_rows.append([
            lib, ref, cat, fourn, str(qte), str(seuil),
            f"{int(cess)}", f"{int(pv)}",
            f"{tva}".replace(",", "."),
            "",  # date_expiration : non fournie par le listing
            "Prix sourcés du listing Laborex Garoua 08/2025 (cession/public)"
        ])

    os.makedirs("data", exist_ok=True)
    with open(OUT, "w", encoding="utf-8-sig", newline="") as f:
        w = csv.writer(f, delimiter=";")
        w.writerow(["nom","reference","categorie","fournisseur","stock","seuil_alerte",
                    "prix_achat","prix_vente","tva","date_expiration","description"])
        for row in out_rows:
            w.writerow(row)

    print(f"Matches candidats : {n_total}")
    print(f"Lignes ecrites    : {len(out_rows)} -> {OUT}")
    # aperçu par categorie
    from collections import Counter
    c = Counter(r[2] for r in out_rows)
    for cat, n in sorted(c.items(), key=lambda x: -x[1]):
        print(f"  {n:>4}  {cat}")

if __name__ == "__main__":
    main()