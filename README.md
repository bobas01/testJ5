# Refactoring d'un Système de Rapport de Commandes Legacy

Ce projet présente le refactoring d'un script PHP legacy tout en garantissant sa non-régression fonctionnelle.

---

## Table des matières

- Installation
- Exécution
- Choix de Refactoring
- Architecture Choisie
- Exemples Concrets
- Limites et Améliorations Futures

---

## Installation

### Prérequis

- PHP version 8.0 ou supérieure
- Composer version 2.0 ou supérieure

### Commandes

```bash
composer install
```

## Exécution

### Exécuter le code refactoré

```bash
php src/OrderReport.php
```

### Exécuter les tests

```bash
composer test
```

Le test golden master compare automatiquement les sorties caractère par caractère.

---

## Choix de Refactoring

### Problèmes Identifiés dans le Legacy

#### 1. God Function / Long Method

Le script legacy contenait une fonction `run()` de 373 lignes qui faisait absolument tout :

- Parsing de 5 fichiers CSV différents
- Calculs de remises, taxes, frais de port
- Formatage de texte
- Écriture de fichiers

Impact: Impossible à tester unitairement, difficile à comprendre, modification risquée.

#### 2.Duplication de Code

Le parsing CSV était implémenté 5 fois différemment :

- `fopen()` + `fgetcsv()` pour customers
- `fopen()` + `fgetcsv()` avec try/catch pour products
- `file_get_contents()` + `explode()` + `str_getcsv()` pour shipping zones
- `file()` + `str_getcsv()` pour promotions
- `file()` + `str_getcsv()` avec validation pour orders

Impact : Maintenance difficile, bugs potentiels, incohérences.

#### 3. Magic Numbers et Constantes Globales

Constantes définies globalement avec `define()` :

```php
define('TAX', 0.2);
define('SHIPPING_LIMIT', 50);
define('MAX_DISCOUNT', 200);
// ... etc
```

Impact : Pas de contexte, difficile à documenter, risque de collision de noms.

#### 4. Mélange des Responsabilités (SRP Violation)

Une seule fonction gérait :

- La lecture de fichiers (I/O)
- Les calculs métier (business logic)
- Le formatage (presentation)
- L'écriture de fichiers (I/O)

Impact : Impossible d'isoler les tests, réutilisation nulle, couplage fort.

#### 5. Absence de Typage

Tout était des tableaux associatifs (`array`) :

```php
$customers[$row[0]] = [
    'id' => $row[0],
    'name' => $row[1],
    // ...
];
```

Impact : Pas d'autocomplétion, erreurs détectées à l'exécution, pas de documentation implicite.

#### 6. Gestion d'Erreurs Silencieuse

```php
try {
    // ...
} catch (Exception $e) {
    // Skip silencieux
    continue;
}
```

Impact : Bugs cachés, données corrompues ignorées, debugging difficile.

#### 7. Règles Métier Cachées

Des règles complexes étaient enfouies dans le code :

- Bonus matin (si heure < 10h)
- Bonus weekend (si jour = samedi/dimanche)
- Majoration zones éloignées (ZONE3, ZONE4)
- Frais de manutention selon nombre d'items

Impact : Comportement non documenté, difficile à modifier, risque de régression.

### Solutions Apportées

#### 1. Séparation des Responsabilités

Création de modules dédiés :

- `CsvReader` : Unification du parsing CSV
- `Calculators/` : Logique métier isolée (remises, taxes, frais de port, devises)
- `Formatters/` : Formatage séparé de la logique
- `IO/ReportWriter` : I/O isolé

Justification : Chaque module a une responsabilité unique, testable indépendamment.

#### 2. Modèles Typés (Value Objects)

Création de classes immutables pour les entités :

- `Customer`, `Order`, `Product`, `Promotion`, `ShippingZone`

Justification :

- Typage fort (PHP 8.0+)
- Autocomplétion IDE
- Documentation implicite
- Validation à la construction

Exemple :

```php
class Customer
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $level,
        public readonly string $shippingZone,
        public readonly string $currency
    ) {}
}
```

#### 3. Extraction des Calculs

Création de classes de calculs pures (statiques) :

- `DiscountCalculator` : Remises volume, fidélité, plafond
- `TaxCalculator` : Calcul taxes avec gestion produits non taxables
- `ShippingCalculator` : Frais de port complexes (paliers, zones)
- `CurrencyConverter` : Conversion devises

Justification :

- Fonctions pures facilement testables
- Pas d'effets de bord
- Réutilisables

#### 4. Constantes Nommées dans une Classe

Remplacement des `define()` globaux par une classe de configuration :

```php
class OrderConfig
{
    public const TAX = 0.2;
    public const SHIPPING_LIMIT = 50.0;
    public const MAX_DISCOUNT = 200.0;
    // ...
}
```

Justification :

- Namespace propre
- Autocomplétion
- Documentation centralisée

#### 5. Unification du Parsing CSV

Une seule classe `CsvReader` avec des méthodes statiques pour chaque type :

- `readCustomers()`, `readProducts()`, `readOrders()`, etc.

Justification :

- Code DRY
- Gestion d'erreurs cohérente
- Facile à étendre

#### 6. Isolation des Effets de Bord

Création de `ReportWriter` pour séparer I/O :

- `writeToConsole()` : Affichage
- `writeJsonToFile()` : Écriture fichier

Justification :

- Testable (mockable)
- Réutilisable
- Pas de side effects cachés

#### 7. Formatage Dédié

Classe `ReportFormatter` pour le formatage :

- `formatCustomerReport()` : Formatage d'un client
- `formatSummary()` : Résumé global
- `formatReport()` : Assemblage final

Justification :

- Logique de présentation isolée
- Facile à modifier le format
- Testable indépendamment

### Architecture Choisie

#### Structure de Fichiers

```
src/
├── OrderReport.php          # Point d'entrée principal
├── CsvReader.php            # Parsing CSV unifié
├── OrderConfig.php          # Constantes de configuration
│
├── Models/                  # Entités métier typées
│   ├── Customer.php
│   ├── Order.php
│   ├── Product.php
│   ├── Promotion.php
│   └── ShippingZone.php
│
├── Calculators/            # Logique de calcul pure
│   ├── DiscountCalculator.php
│   ├── TaxCalculator.php
│   ├── ShippingCalculator.php
│   └── CurrencyConverter.php
│
├── Formatters/              # Formatage de sortie
│   └── ReportFormatter.php
│
└── IO/                      # Opérations I/O
    └── ReportWriter.php
```

#### Flux de Données

```
1. CsvReader
   ↓ (lit fichiers CSV)
   ↓ (retourne modèles typés)

2. OrderReport::run()
   ↓ (orchestre les calculs)

3. Calculators/
   ↓ (calculs purs)
   ↓ (retournent valeurs numériques)

4. Formatters/
   ↓ (formatage texte)
   ↓ (retourne chaînes formatées)

5. IO/ReportWriter
   ↓ (écriture console + fichier)
```

#### Principes Appliqués

- Single Responsibility Principle (SRP) : Chaque classe a une seule responsabilité
- Don't Repeat Yourself (DRY) : Parsing CSV unifié, calculs extraits
- Separation of Concerns : I/O, calculs, formatage séparés
- Pure Functions : Calculs sans effets de bord, testables
- Immutability : Modèles en `readonly`, pas de mutation

### Exemples Concrets

#### Exemple 1 : Extraction du Calcul de Remise

Problème : Code legacy avec magic numbers et logique complexe enfouie

```php
// Legacy : 50+ lignes de calculs mélangés dans run()
$disc = 0.0;
if ($sub > 50) {
    $disc = $sub * 0.05;
}
if ($sub > 100) {
    $disc = $sub * 0.10; // écrase précédent
}
// ... règles weekend, plafond, etc.
```

Solution : Extraction dans `DiscountCalculator` avec méthodes nommées

```php
// Refactoré : Logique isolée et testable
$volumeDiscount = DiscountCalculator::calculateVolumeDiscount($sub, $level);
$volumeDiscount = DiscountCalculator::applyWeekendBonus($volumeDiscount, $firstOrderDate);
$loyaltyDiscount = DiscountCalculator::calculateLoyaltyDiscount($pts);
$discounts = DiscountCalculator::applyMaxDiscountCap($volumeDiscount, $loyaltyDiscount);
```

Bénéfices :

- Testable unitairement
- Compréhensible (noms explicites)
- Réutilisable

#### Exemple 2 : Typage avec Modèles

Problème : Tableaux associatifs non typés

```php
// Legacy : Accès par clé string, pas de validation
$cust = $customers[$cid] ?? [];
$name = $cust['name'] ?? 'Unknown';
$level = $cust['level'] ?? 'BASIC';
```

Solution : Classes typées avec propriétés readonly

```php
// Refactoré : Typage fort, autocomplétion
$cust = $customers[$cid] ?? null;
$name = $cust?->name ?? 'Unknown';
$level = $cust?->level ?? 'BASIC';
```

Bénéfices :

- Erreurs détectées à la compilation
- Autocomplétion IDE
- Documentation implicite

#### Exemple 3 : Unification du Parsing CSV

Problème : 5 implémentations différentes du parsing

```php
// Legacy : Variations multiples
$custFile = fopen($custPath, 'r');
// ... vs ...
$shipContent = file_get_contents($shipPath);
$shipLines = explode("\n", $shipContent);
// ... vs ...
$promoLines = @file($promoPath, FILE_IGNORE_NEW_LINES);
```

Solution: Classe `CsvReader` unifiée

```php
// Refactoré : Interface cohérente
$customers = CsvReader::readCustomers($custPath);
$products = CsvReader::readProducts($prodPath);
$shippingZones = CsvReader::readShippingZones($shipPath);
```

Bénéfices :

- Code DRY
- Gestion d'erreurs cohérente
- Facile à maintenir

#### Exemple 4 : Isolation des Calculs de Taxe

Problème : Logique complexe mélangée avec formatage

```php
// Legacy : 30+ lignes dans run() avec vérifications imbriquées
$allTaxable = true;
foreach ($totalsByCustomer[$cid]['items'] as $item) {
    //  vérification
}
if ($allTaxable) {
    $tax = round($taxable * TAX, 2);
} else {
    // calcul par ligne
}
```

Solution : Classe `TaxCalculator` dédiée

```php
// Refactoré : Logique isolée et testable
$tax = TaxCalculator::calculateTax(
    $taxable,
    $totalsByCustomer[$cid]['items'],
    $products
);
```

Bénéfices :

- Testable indépendamment
- Compréhensible (nom explicite)
- Réutilisable

---

## Limites et Améliorations Futures

### Ce qui n'a pas été fait (par manque de temps)

- Refactoring complet de la logique de groupement : Le groupement par client reste dans `OrderReport::run()`, pourrait être extrait dans une classe `OrderAggregator`
- Gestion d'erreurs explicite : Les exceptions sont toujours catchées silencieusement dans `CsvReader`, pourrait loguer ou remonter
- Validation des données : Pas de validation stricte des CSV (colonnes manquantes, types incorrects)
- Configuration externalisée : Les constantes sont en dur, pourrait être dans un fichier de config
- Tests d'intégration complets : Seuls les tests unitaires et le golden master existent, pas de tests avec jeux de données variés

### Compromis Assumés

#### 1. Préservation des Bugs Legacy

Certains bugs identifiés ont été intentionnellement préservés pour garantir la non-régression :

- Bug de remise FIXED appliquée par ligne au lieu de global
- Écrasement des remises par paliers (if successifs sans else)

Justification : Le test golden master exige une sortie identique, bugs inclus. La correction aurait changé le comportement observable.

#### 2. Parsing CSV Conservé

Le parsing CSV utilise toujours les mêmes méthodes natives PHP (`fgetcsv`, `str_getcsv`) malgré leurs limitations.

Justification : Éviter d'introduire des dépendances externes (bibliothèques CSV) qui pourraient changer le comportement.

#### 3. Structure de Données Intermédiaire

Le groupement par client utilise encore un tableau associatif (`$totalsByCustomer`) au lieu d'un objet dédié.

Justification : Complexité ajoutée vs bénéfice limité dans le temps imparti. Amélioration future possible.

#### 4. Namespace Simple

Utilisation d'un seul namespace `OrderReport` au lieu d'une structure plus granulaire (`OrderReport\Domain`, `OrderReport\Infrastructure`, etc.).

Justification\*: Structure suffisante pour la taille du projet, évite la sur-ingénierie.

### Pistes d'Amélioration Future

#### Amélioration future

1. Logging

   - Ajouter un système de logging pour tracer les erreurs de parsing
   - Logger les calculs pour debugging

2. BDD

   - Avoir une base de données pour ne plus avoir les données en dure.
