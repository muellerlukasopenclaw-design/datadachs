<?php
/**
 * DataDachs – PII-Erkennungsregeln
 * Spaltennamen-Heuristik + Regex-Muster
 */

return [
    // === SPALTENNAMEN-HEURISTIK ===
    // Key = normalisierter Spaltenname, Value = [typ, faker_methode, gewichtung]
    'column_rules' => [
        // Namen
        'firstname'      => ['type' => 'first_name',   'faker' => 'firstName',    'weight' => 10],
        'first_name'     => ['type' => 'first_name',   'faker' => 'firstName',    'weight' => 10],
        'vorname'        => ['type' => 'first_name',   'faker' => 'firstName',    'weight' => 10],
        'lastname'       => ['type' => 'last_name',    'faker' => 'lastName',     'weight' => 10],
        'last_name'      => ['type' => 'last_name',    'faker' => 'lastName',     'weight' => 10],
        'nachname'       => ['type' => 'last_name',    'faker' => 'lastName',     'weight' => 10],
        'name'           => ['type' => 'full_name',    'faker' => 'name',         'weight' => 8],
        'fullname'       => ['type' => 'full_name',    'faker' => 'name',         'weight' => 8],
        'full_name'      => ['type' => 'full_name',    'faker' => 'name',         'weight' => 8],
        
        // Kontakt
        'email'          => ['type' => 'email',        'faker' => 'safeEmail',    'weight' => 10],
        'mail'           => ['type' => 'email',        'faker' => 'safeEmail',    'weight' => 9],
        'e_mail'         => ['type' => 'email',        'faker' => 'safeEmail',    'weight' => 10],
        'phone'          => ['type' => 'phone',        'faker' => 'phoneNumber',  'weight' => 10],
        'telefon'        => ['type' => 'phone',        'faker' => 'phoneNumber',  'weight' => 10],
        'mobile'         => ['type' => 'phone',        'faker' => 'phoneNumber',  'weight' => 9],
        'mobilnummer'    => ['type' => 'phone',        'faker' => 'phoneNumber',  'weight' => 10],
        'fax'            => ['type' => 'phone',        'faker' => 'phoneNumber',  'weight' => 8],
        
        // Adresse
        'street'         => ['type' => 'street',       'faker' => 'streetName',   'weight' => 10],
        'strasse'        => ['type' => 'street',       'faker' => 'streetName',   'weight' => 10],
        'straße'         => ['type' => 'street',       'faker' => 'streetName',   'weight' => 10],
        'address'        => ['type' => 'street',       'faker' => 'streetAddress','weight' => 9],
        'adresse'        => ['type' => 'street',       'faker' => 'streetAddress','weight' => 9],
        'housenumber'    => ['type' => 'house_number', 'faker' => 'buildingNumber','weight' => 8],
        'hausnummer'     => ['type' => 'house_number', 'faker' => 'buildingNumber','weight' => 8],
        'zip'            => ['type' => 'postcode',     'faker' => 'postcode',     'weight' => 10],
        'plz'            => ['type' => 'postcode',     'faker' => 'postcode',     'weight' => 10],
        'postal_code'    => ['type' => 'postcode',     'faker' => 'postcode',     'weight' => 10],
        'city'           => ['type' => 'city',         'faker' => 'city',         'weight' => 10],
        'stadt'          => ['type' => 'city',         'faker' => 'city',         'weight' => 10],
        'ort'            => ['type' => 'city',         'faker' => 'city',         'weight' => 10],
        'country'        => ['type' => 'country',      'faker' => 'country',      'weight' => 6],
        'land'           => ['type' => 'country',      'faker' => 'country',      'weight' => 6],
        
        // Persönliche Daten
        'birthdate'      => ['type' => 'birthdate',    'faker' => 'date',         'weight' => 10],
        'birthday'       => ['type' => 'birthdate',    'faker' => 'date',         'weight' => 10],
        'geburtsdatum'   => ['type' => 'birthdate',    'faker' => 'date',         'weight' => 10],
        'dob'            => ['type' => 'birthdate',    'faker' => 'date',         'weight' => 10],
        'age'            => ['type' => 'age',          'faker' => 'numberBetween','weight' => 5],
        'alter'          => ['type' => 'age',          'faker' => 'numberBetween','weight' => 5],
        
        // Finanzen
        'iban'           => ['type' => 'iban',         'faker' => 'iban',         'weight' => 10],
        'bic'            => ['type' => 'bic',          'faker' => 'swiftBicNumber','weight' => 10],
        'bank'           => ['type' => 'bank',         'faker' => 'company',      'weight' => 6],
        'konto'          => ['type' => 'iban',         'faker' => 'iban',         'weight' => 8],
        'account'        => ['type' => 'iban',         'faker' => 'iban',         'weight' => 7],
        
        // Organisation
        'company'        => ['type' => 'company',      'faker' => 'company',      'weight' => 8],
        'firma'          => ['type' => 'company',      'faker' => 'company',      'weight' => 8],
        'organisation'   => ['type' => 'company',      'faker' => 'company',      'weight' => 8],
        'org'            => ['type' => 'company',      'faker' => 'company',      'weight' => 7],
        
        // Login
        'username'       => ['type' => 'username',     'faker' => 'userName',     'weight' => 9],
        'user_name'      => ['type' => 'username',     'faker' => 'userName',     'weight' => 9],
        'login'          => ['type' => 'username',     'faker' => 'userName',     'weight' => 8],
        'password'       => ['type' => 'password',     'faker' => 'password',     'weight' => 10],
        'passwort'       => ['type' => 'password',     'faker' => 'password',     'weight' => 10],
        
        // Netzwerk
        'ip'             => ['type' => 'ip',           'faker' => 'ipv4',         'weight' => 10],
        'ip_address'     => ['type' => 'ip',           'faker' => 'ipv4',         'weight' => 10],
        'ipv4'           => ['type' => 'ip',           'faker' => 'ipv4',         'weight' => 10],
        'ipv6'           => ['type' => 'ip',           'faker' => 'ipv6',         'weight' => 10],
        'mac'            => ['type' => 'mac',          'faker' => 'macAddress',   'weight' => 9],
        'mac_address'    => ['type' => 'mac',          'faker' => 'macAddress',   'weight' => 9],
        'hostname'       => ['type' => 'domain',       'faker' => 'domainName',   'weight' => 6],
        'url'            => ['type' => 'url',          'faker' => 'url',          'weight' => 7],
        'website'        => ['type' => 'url',          'faker' => 'url',          'weight' => 7],
        'webseite'       => ['type' => 'url',          'faker' => 'url',          'weight' => 7],
        
        // IDs (nur wenn personenbezogen)
        'ssn'            => ['type' => 'ssn',          'faker' => 'randomNumber', 'weight' => 10],
        'tax_id'         => ['type' => 'tax_id',       'faker' => 'randomNumber', 'weight' => 10],
        'steuernummer'   => ['type' => 'tax_id',       'faker' => 'randomNumber', 'weight' => 10],
        'person_id'      => ['type' => 'uuid',         'faker' => 'uuid',         'weight' => 7],
        'customer_id'    => ['type' => 'uuid',         'faker' => 'uuid',         'weight' => 6],
        'employee_id'    => ['type' => 'uuid',         'faker' => 'uuid',         'weight' => 6],
        'member_id'      => ['type' => 'uuid',         'faker' => 'uuid',         'weight' => 6],
    ],
    
    // === REGEX-MUSTER ===
    // Key = typ, Value = [regex, gewichtung]
    'regex_patterns' => [
        'email'          => ['pattern' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', 'weight' => 10],
        'phone_de'       => ['pattern' => '/(?:\+49|0)[\s\-/]?[\d\s\-/]{6,20}/', 'weight' => 9],
        'iban'           => ['pattern' => '/[A-Z]{2}\d{2}[\s]?[A-Z0-9]{4}[\s]?[A-Z0-9]{4}[\s]?[A-Z0-9]{4}[\s]?[A-Z0-9]{4}[\s]?[A-Z0-9]{4}[\s]?[A-Z0-9]{2}/', 'weight' => 10],
        'ipv4'           => ['pattern' => '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/', 'weight' => 10],
        'ipv6'           => ['pattern' => '/(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}/', 'weight' => 10],
        'url'            => ['pattern' => '/https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)/', 'weight' => 8],
        'uuid'           => ['pattern' => '/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', 'weight' => 7],
        'postcode_de'    => ['pattern' => '/\b\d{5}\b/', 'weight' => 7],
        'date_iso'       => ['pattern' => '/\d{4}-\d{2}-\d{2}/', 'weight' => 6],
        'date_de'        => ['pattern' => '/\d{2}\.\d{2}\.\d{4}/', 'weight' => 6],
    ],
    
    // === KONTEXT-SIGNALE (Tabellennamen) ===
    // Tabellennamen, die PII-Wahrscheinlichkeit erhöhen
    'table_context' => [
        'users'          => 1.5,
        'customers'      => 1.5,
        'contacts'       => 1.5,
        'members'        => 1.5,
        'persons'        => 1.5,
        'people'         => 1.5,
        'employees'      => 1.5,
        'accounts'       => 1.3,
        'clients'        => 1.4,
        'patients'       => 1.5,
        'students'       => 1.4,
        'orders'         => 1.2,
        'invoices'       => 1.2,
        'leads'          => 1.3,
    ],
    
    // === FAKER-LOKALE ===
    'faker_locale' => 'de_DE',
    
    // === SICHERE E-MAIL-DOMAINS ===
    'safe_email_domains' => ['example.test', 'example.invalid', 'example.local'],
];
