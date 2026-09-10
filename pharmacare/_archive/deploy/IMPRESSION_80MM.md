# Impression ticket 80 mm — 2 copies sans configurer l'imprimante

L'application imprime les tickets 80 mm via le navigateur (`window.print()`).
Pour obtenir **2 copies silencieuses** sans boîte de dialogue et sans pré-réglage
du nombre de copies sur l'imprimante, on utilise le **mode kiosk** de Chrome/Edge :
chaque appel `window.print()` sort un ticket silencieusement, et l'app appelle
`print()` N fois (N = paramètre `ticket_copies`, défaut 2).

## 1. Imprimante 80 mm = imprimante par défaut de Windows

Paramètres Windows → Bluetooth & périphériques → Imprimantes & scanners →
sélectionner l'imprimante 80 mm → **Définir par défaut**.

Aucun réglage « nombre de copies » n'est nécessaire : c'est l'app qui imprime
N fois, pas le pilote.

## 2. Lancer Chrome/Edge en mode kiosk impression

Fermer toutes les fenêtres Chrome/Edge, puis lancer (adaptez l'URL) :

**Chrome :**
```
"C:\Program Files\Google\Chrome\Application\chrome.exe" ^
  --kiosk --kiosk-printing ^
  http://localhost/pharmacare
```

**Edge :**
```
"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" ^
  --kiosk --kiosk-printing ^
  http://localhost/pharmacare
```

- `--kiosk` : plein écran, sans barre d'adresse ni menus.
- `--kiosk-printing` : chaque `window.print()` imprime **silencieusement** sur
  l'imprimante par défaut (pas de boîte de dialogue).

## 3. Nombre de copies (optionnel)

Par défaut 2 copies. Pour changer, insérer une ligne dans `parametres` :

```sql
INSERT INTO parametres (cle, valeur) VALUES ('ticket_copies', '2')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
```

`printReceipt80()` lit ce paramètre ; `1` = 1 copie, `3` = 3 copies, etc.

## Comportement hors kiosk

Sans `--kiosk-printing`, chaque `window.print()` ouvre la boîte de dialogue
d'impression standard (l'utilisateur valide N fois). Le ticket reste correct,
mais pas silencieux — d'où l'intérêt du mode kiosk en caisse.