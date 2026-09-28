-- 1. Maak Aangepaste Datatypes (Enums)
CREATE TYPE user_role_enum AS ENUM ('Superadmin', 'Admin', 'Trainer', 'Coach');

-- 2. Gebruikers Tabel
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    naam VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    last_login TIMESTAMP WITH TIME ZONE,
    login_count INT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Wachtwoorden Tabel (met actieve versie-constructie)
CREATE TABLE passwords (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    encrypted_password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. Verenigingen / Clubs Tabel
CREATE TABLE clubs (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. Teams Tabel
CREATE TABLE teams (
    id SERIAL PRIMARY KEY,
    club_id INT NOT NULL REFERENCES clubs(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 6. Koppeltabel Gebruikers, Rollen, Clubs en Teams
CREATE TABLE user_roles (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role user_role_enum NOT NULL,
    club_id INT REFERENCES clubs(id) ON DELETE CASCADE, -- Ingevuld voor Admin/Trainer/Coach
    team_id INT REFERENCES teams(id) ON DELETE CASCADE,  -- Ingevuld voor Trainer/Coach (NULL voor Admin/Superadmin)
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,

    -- Zorg ervoor dat een combinatie niet dubbel wordt ingevoerd
    CONSTRAINT unique_user_club_team UNIQUE NULLS NOT DISTINCT (user_id, club_id, team_id)
);

-- 7. Performance Indexen
CREATE INDEX idx_passwords_user_active ON passwords(user_id, is_active);
CREATE INDEX idx_user_roles_user ON user_roles(user_id);
CREATE INDEX idx_teams_club ON teams(club_id);
