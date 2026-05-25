-- Réinitialiser les mots de passe à 'password'
UPDATE utilisateurs SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username IN ('admin', 'manager');