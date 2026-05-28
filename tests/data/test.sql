-- DataDachs Testdaten: SQL
INSERT INTO users (id, firstname, lastname, email, phone, birthdate, street, city, zip)
VALUES (1, 'Max', 'Müller', 'max.mueller@example.com', '+49 211 123456', '1985-04-12', 'Hauptstraße 1', 'Düsseldorf', '40210');

INSERT INTO users (id, firstname, lastname, email, phone, birthdate, street, city, zip)
VALUES (2, 'Anna', 'Schmidt', 'anna.schmidt@example.com', '+49 30 987654', '1990-08-23', 'Berliner Straße 42', 'Berlin', '10115');

INSERT INTO users (id, firstname, lastname, email, phone, birthdate, street, city, zip)
VALUES (3, 'Jürgen', 'Größer', 'juergen.groesser@example.com', '+49 89 555555', '1978-12-05', 'Münchener Weg 7', 'München', '80331');

-- Multi-Row Insert
INSERT INTO customers (id, company, contact_email, iban)
VALUES 
(10, 'Müller GmbH', 'info@mueller-gmbh.de', 'DE89370400440532013000'),
(11, 'Schmidt & Co', 'kontakt@schmidt-co.de', 'DE75512108001245126199'),
(12, 'Größer AG', 'vertrieb@groesser-ag.de', 'DE89370400440532013000');

-- Mit NULL und Zahlen
INSERT INTO orders (id, user_id, amount, status, created_at)
VALUES (100, 1, 249.99, 'completed', '2024-01-15 10:30:00');
