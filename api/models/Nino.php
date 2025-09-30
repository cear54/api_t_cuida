<?php
class Nino {
    private $conn;
    private $table_name = "ninos";

    public $id;
    public $nombre;
    public $apellido_paterno;
    public $apellido_materno;
    public $fecha_nacimiento;
    public $genero;
    public $grupo_id;
    public $curp;
    public $imagen;
    public $tiene_alergias;
    public $alergias;
    public $toma_medicamentos;
    public $medicamentos;
    public $tiene_condiciones_medicas;
    public $condiciones_medicas;
    public $contacto_emergencia;
    public $parentesco_emergencia;
    public $telefono_emergencia;
    public $imagen_contacto_1;
    public $email_emergencia;
    public $contacto_emergencia_2;
    public $parentesco_emergencia_2;
    public $telefono_emergencia_2;
    public $imagen_contacto_2;
    public $email_emergencia_2;
    public $contacto_emergencia_3;
    public $parentesco_emergencia_3;
    public $telefono_emergencia_3;
    public $imagen_contacto_3;
    public $email_emergencia_3;
    public $activo;
    public $fecha_inscripcion;
    public $fecha_creacion;
    public $fecha_actualizacion;
    public $empresa_id;
    public $salon_id;
    public $salon_nombre;
    public $contacto_emergencia_4;
    public $parentesco_emergencia_4;
    public $telefono_emergencia_4;
    public $imagen_contacto_4;
    public $email_emergencia_4;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Obtener niño por ID
    public function getNinoById() {
        $query = "SELECT n.id, n.nombre, n.apellido_paterno, n.apellido_materno, n.fecha_nacimiento, n.genero, 
                         n.grupo_id, n.curp, n.imagen, n.tiene_alergias, n.alergias, n.toma_medicamentos, 
                         n.medicamentos, n.tiene_condiciones_medicas, n.condiciones_medicas,
                         n.contacto_emergencia, n.parentesco_emergencia, n.telefono_emergencia, 
                         n.imagen_contacto_1, n.email_emergencia, n.contacto_emergencia_2, 
                         n.parentesco_emergencia_2, n.telefono_emergencia_2, n.imagen_contacto_2, 
                         n.email_emergencia_2, n.contacto_emergencia_3, n.parentesco_emergencia_3, 
                         n.telefono_emergencia_3, n.imagen_contacto_3, n.email_emergencia_3,
                         n.activo, n.fecha_inscripcion, n.fecha_creacion, n.fecha_actualizacion, 
                         n.empresa_id, n.salon_id, n.contacto_emergencia_4, n.parentesco_emergencia_4, 
                         n.telefono_emergencia_4, n.imagen_contacto_4, n.email_emergencia_4,
                         s.nombre as salon_nombre
                  FROM " . $this->table_name . " n
                  LEFT JOIN salones s ON n.grupo_id = s.id
                  WHERE n.id = :id 
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        $num = $stmt->rowCount();

        if($num > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->id = $row['id'];
            $this->nombre = $row['nombre'];
            $this->apellido_paterno = $row['apellido_paterno'];
            $this->apellido_materno = $row['apellido_materno'];
            $this->fecha_nacimiento = $row['fecha_nacimiento'];
            $this->genero = $row['genero'];
            $this->grupo_id = $row['grupo_id'];
            $this->curp = $row['curp'];
            $this->imagen = $row['imagen'];
            $this->tiene_alergias = $row['tiene_alergias'];
            $this->alergias = $row['alergias'];
            $this->toma_medicamentos = $row['toma_medicamentos'];
            $this->medicamentos = $row['medicamentos'];
            $this->tiene_condiciones_medicas = $row['tiene_condiciones_medicas'];
            $this->condiciones_medicas = $row['condiciones_medicas'];
            $this->contacto_emergencia = $row['contacto_emergencia'];
            $this->parentesco_emergencia = $row['parentesco_emergencia'];
            $this->telefono_emergencia = $row['telefono_emergencia'];
            $this->imagen_contacto_1 = $row['imagen_contacto_1'];
            $this->email_emergencia = $row['email_emergencia'];
            $this->contacto_emergencia_2 = $row['contacto_emergencia_2'];
            $this->parentesco_emergencia_2 = $row['parentesco_emergencia_2'];
            $this->telefono_emergencia_2 = $row['telefono_emergencia_2'];
            $this->imagen_contacto_2 = $row['imagen_contacto_2'];
            $this->email_emergencia_2 = $row['email_emergencia_2'];
            $this->contacto_emergencia_3 = $row['contacto_emergencia_3'];
            $this->parentesco_emergencia_3 = $row['parentesco_emergencia_3'];
            $this->telefono_emergencia_3 = $row['telefono_emergencia_3'];
            $this->imagen_contacto_3 = $row['imagen_contacto_3'];
            $this->email_emergencia_3 = $row['email_emergencia_3'];
            $this->activo = $row['activo'];
            $this->fecha_inscripcion = $row['fecha_inscripcion'];
            $this->fecha_creacion = $row['fecha_creacion'];
            $this->fecha_actualizacion = $row['fecha_actualizacion'];
            $this->empresa_id = $row['empresa_id'];
            $this->salon_id = $row['salon_id'];
            $this->salon_nombre = $row['salon_nombre'];
            $this->contacto_emergencia_4 = $row['contacto_emergencia_4'];
            $this->parentesco_emergencia_4 = $row['parentesco_emergencia_4'];
            $this->telefono_emergencia_4 = $row['telefono_emergencia_4'];
            $this->imagen_contacto_4 = $row['imagen_contacto_4'];
            $this->email_emergencia_4 = $row['email_emergencia_4'];
            
            return true;
        }
        return false;
    }

    // Crear niño
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET nombre=:nombre, apellidos=:apellidos, fecha_nacimiento=:fecha_nacimiento, 
                      genero=:genero, personal_id=:personal_id, empresa_id=:empresa_id";

        $stmt = $this->conn->prepare($query);

        // Sanitizar datos
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->apellidos = htmlspecialchars(strip_tags($this->apellidos));
        $this->fecha_nacimiento = htmlspecialchars(strip_tags($this->fecha_nacimiento));
        $this->genero = htmlspecialchars(strip_tags($this->genero));
        $this->personal_id = htmlspecialchars(strip_tags($this->personal_id));
        $this->empresa_id = htmlspecialchars(strip_tags($this->empresa_id));

        // Bind parameters
        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':apellidos', $this->apellidos);
        $stmt->bindParam(':fecha_nacimiento', $this->fecha_nacimiento);
        $stmt->bindParam(':genero', $this->genero);
        $stmt->bindParam(':personal_id', $this->personal_id);
        $stmt->bindParam(':empresa_id', $this->empresa_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>
