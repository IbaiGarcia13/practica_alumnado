<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$errores = [];
$mensaje = $_GET['mensaje'] ?? '';
$alumnoEditar = null;
$datosFormulario = [
    'id' => '',
    'nombre' => '',
    'apellidos' => '',
    'fecha_nacimiento' => '',
    'curso' => '',
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'eliminar') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $sentencia = $pdo->prepare('DELETE FROM alumnos WHERE id = ?');
            $sentencia->execute([$id]);
            header('Location: index.php?mensaje=Alumno eliminado correctamente');
            exit;
        }
        $errores[] = 'El alumno indicado no es válido.';
    }

    if ($accion === 'guardar') {
        $datosFormulario = [
            'id' => trim($_POST['id'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'apellidos' => trim($_POST['apellidos'] ?? ''),
            'fecha_nacimiento' => trim($_POST['fecha_nacimiento'] ?? ''),
            'curso' => trim($_POST['curso'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
        ];
        $password = $_POST['password'] ?? '';

        if ($datosFormulario['nombre'] === '') {
            $errores[] = 'El nombre es obligatorio.';
        }
        if ($datosFormulario['apellidos'] === '') {
            $errores[] = 'Los apellidos son obligatorios.';
        }
        $fechaNacimiento = DateTime::createFromFormat('!Y-m-d', $datosFormulario['fecha_nacimiento']);
        $erroresFecha = DateTime::getLastErrors();
        $fechaValida = $fechaNacimiento !== false
            && ($erroresFecha === false || ($erroresFecha['warning_count'] === 0 && $erroresFecha['error_count'] === 0));
        if ($datosFormulario['fecha_nacimiento'] === '') {
            $errores[] = 'La fecha de nacimiento es obligatoria.';
        } elseif (!$fechaValida) {
            $errores[] = 'La fecha de nacimiento no es válida.';
        }
        if (!in_array($datosFormulario['curso'], ['1', '2', '3', '4'], true)) {
            $errores[] = 'Selecciona un curso válido.';
        }
        if (!filter_var($datosFormulario['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Introduce un email válido.';
        }
        if ($datosFormulario['id'] === '' && $password === '') {
            $errores[] = 'La contraseña es obligatoria.';
        }

        if (!$errores) {
            try {
                $pdo->beginTransaction();
                $id = filter_var($datosFormulario['id'], FILTER_VALIDATE_INT);

                $consulta = $pdo->prepare('SELECT COUNT(*) FROM alumnos WHERE curso = ? AND id <> ?');
                $consulta->execute([$datosFormulario['curso'], $id ?: 0]);
                if ((int) $consulta->fetchColumn() >= 25) {
                    $errores[] = 'Ese curso ya tiene 25 alumnos matriculados.';
                } elseif ($id) {
                    if ($password !== '') {
                        $sentencia = $pdo->prepare('UPDATE alumnos SET nombre = ?, apellidos = ?, fecha_nacimiento = ?, curso = ?, email = ?, password = ? WHERE id = ?');
                        $sentencia->execute([
                            $datosFormulario['nombre'],
                            $datosFormulario['apellidos'],
                            $datosFormulario['fecha_nacimiento'],
                            $datosFormulario['curso'],
                            $datosFormulario['email'],
                            password_hash($password, PASSWORD_DEFAULT),
                            $id,
                        ]);
                    } else {
                        $sentencia = $pdo->prepare('UPDATE alumnos SET nombre = ?, apellidos = ?, fecha_nacimiento = ?, curso = ?, email = ? WHERE id = ?');
                        $sentencia->execute([
                            $datosFormulario['nombre'],
                            $datosFormulario['apellidos'],
                            $datosFormulario['fecha_nacimiento'],
                            $datosFormulario['curso'],
                            $datosFormulario['email'],
                            $id,
                        ]);
                    }
                    $pdo->commit();
                    header('Location: index.php?mensaje=Alumno actualizado correctamente');
                    exit;
                } else {
                    $sentencia = $pdo->prepare('INSERT INTO alumnos (nombre, apellidos, fecha_nacimiento, curso, email, password) VALUES (?, ?, ?, ?, ?, ?)');
                    $sentencia->execute([
                        $datosFormulario['nombre'],
                        $datosFormulario['apellidos'],
                        $datosFormulario['fecha_nacimiento'],
                        $datosFormulario['curso'],
                        $datosFormulario['email'],
                        password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    $pdo->commit();
                    header('Location: index.php?mensaje=Alumno añadido correctamente');
                    exit;
                }
                $pdo->rollBack();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errores[] = $exception->errorInfo[1] === 1062
                    ? 'Ese email ya está registrado.'
                    : 'No se han podido guardar los datos.';
            }
        }
    }
}

if (isset($_GET['editar'])) {
    $id = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
    if ($id) {
        $sentencia = $pdo->prepare('SELECT id, nombre, apellidos, fecha_nacimiento, curso, email FROM alumnos WHERE id = ?');
        $sentencia->execute([$id]);
        $alumnoEditar = $sentencia->fetch();
        if ($alumnoEditar) {
            $datosFormulario = $alumnoEditar;
        }
    }
}

$alumnos = $pdo->query('SELECT id, nombre, apellidos, fecha_nacimiento, curso, email FROM alumnos ORDER BY apellidos, nombre')->fetchAll();

function escapar(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumnado ESO | CF Somorrostro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="contenedor">
        <header class="cabecera">
            <div>
                <p class="etiqueta">CF Somorrostro / ESO</p>
                <h1>Gestión de alumnado</h1>
                <p class="subtitulo">Altas, cambios y bajas del alumnado matriculado.</p>
            </div>
            <span class="contador"><?= count($alumnos) ?> alumnos</span>
        </header>

        <?php if ($mensaje): ?>
            <p class="alerta exito"><?= escapar($mensaje) ?></p>
        <?php endif; ?>
        <?php if ($errores): ?>
            <div class="alerta error">
                <?php foreach ($errores as $error): ?>
                    <p><?= escapar($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="panel formulario-panel">
            <div class="panel-titulo">
                <h2><?= $alumnoEditar ? 'Editar alumno' : 'Nuevo alumno' ?></h2>
                <?php if ($alumnoEditar): ?><a class="enlace" href="index.php">Cancelar edición</a><?php endif; ?>
            </div>
            <form method="post" action="index.php" class="formulario">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?= escapar((string) $datosFormulario['id']) ?>">
                <label>Nombre <input type="text" name="nombre" maxlength="50" required value="<?= escapar($datosFormulario['nombre']) ?>"></label>
                <label>Apellidos <input type="text" name="apellidos" maxlength="100" required value="<?= escapar($datosFormulario['apellidos']) ?>"></label>
                <label>Fecha de nacimiento <input type="date" name="fecha_nacimiento" required value="<?= escapar($datosFormulario['fecha_nacimiento']) ?>"></label>
                <label>Curso
                    <select name="curso" required>
                        <option value="">Selecciona un curso</option>
                        <?php foreach (['1', '2', '3', '4'] as $curso): ?>
                            <option value="<?= $curso ?>" <?= $datosFormulario['curso'] === $curso ? 'selected' : '' ?>><?= $curso ?>º ESO</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Email de Educamos <input type="email" name="email" maxlength="100" required value="<?= escapar($datosFormulario['email']) ?>"></label>
                <label>Contraseña de Educamos <input type="password" name="password" <?= $alumnoEditar ? '' : 'required' ?> minlength="8" placeholder="<?= $alumnoEditar ? 'Dejar vacía para mantenerla' : '' ?>"></label>
                <button type="submit"><?= $alumnoEditar ? 'Guardar cambios' : 'Añadir alumno' ?></button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-titulo"><h2>Alumnado matriculado</h2><span class="limite">Máximo: 25 por curso</span></div>
            <div class="tabla-contenedor">
                <table>
                    <thead><tr><th>Nombre completo</th><th>Nacimiento</th><th>Curso</th><th>Email</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($alumnos as $alumno): ?>
                        <tr>
                            <td><?= escapar($alumno['nombre'] . ' ' . $alumno['apellidos']) ?></td>
                            <td><?= escapar(date('d/m/Y', strtotime($alumno['fecha_nacimiento']))) ?></td>
                            <td><span class="curso"> <?= escapar($alumno['curso']) ?>º ESO</span></td>
                            <td><?= escapar($alumno['email']) ?></td>
                            <td class="acciones"><a href="?editar=<?= $alumno['id'] ?>">Editar</a><form method="post" onsubmit="return confirm('¿Eliminar este alumno?');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $alumno['id'] ?>"><button class="boton-eliminar" type="submit">Eliminar</button></form></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$alumnos): ?><tr><td colspan="5" class="vacio">Todavía no hay alumnos matriculados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
