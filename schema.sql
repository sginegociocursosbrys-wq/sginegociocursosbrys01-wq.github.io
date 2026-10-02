CREATE TABLE IF NOT EXISTS areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  tipo VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE,
  grupo VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','encargado','docente','auditor') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bienes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  descripcion TEXT NOT NULL,
  categoria_id INT NULL,
  area_id INT NOT NULL,
  responsable VARCHAR(120) NULL,
  estado ENUM('bueno','regular','malo','en_reparacion','baja') NOT NULL DEFAULT 'bueno',
  valor DECIMAL(12,2) NULL,
  fecha_adquisicion DATE NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_bienes_area (area_id),
  FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  FOREIGN KEY (area_id) REFERENCES areas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS movimientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bien_id INT NOT NULL,
  tipo VARCHAR(20) NOT NULL,
  area_origen INT NULL,
  area_destino INT NULL,
  usuario_id INT NULL,
  observacion TEXT NULL,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mov_bien (bien_id),
  FOREIGN KEY (bien_id) REFERENCES bienes(id),
  FOREIGN KEY (area_origen) REFERENCES areas(id),
  FOREIGN KEY (area_destino) REFERENCES areas(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO areas (nombre, tipo) VALUES
 ('Aula 1A','aula'),('Aula 2A','aula'),('Laboratorio de Ciencias','laboratorio'),
 ('Laboratorio de Cómputo','laboratorio'),('Dirección','oficina'),
 ('Secretaría','oficina'),('Biblioteca','biblioteca'),('Almacén','almacen');

INSERT IGNORE INTO categorias (nombre, grupo) VALUES
 ('Pupitre','mobiliario'),('Escritorio','mobiliario'),('Pizarra','mobiliario'),
 ('Computadora','tecnologico'),('Proyector','tecnologico'),('Impresora','tecnologico'),
 ('Microscopio','didactico'),('Libro','didactico'),('Material de laboratorio','didactico');
