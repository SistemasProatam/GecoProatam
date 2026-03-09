<?php
include(__DIR__ . "/../conexion.php");

echo "<h1>Prueba Manual de Transferencia</h1>";

// Verificar tabla concepto_items
echo "<h3>1. Verificando tabla concepto_items:</h3>";
$result = $conn->query("SHOW TABLES LIKE 'concepto_items'");
if ($result->num_rows > 0) {
    echo "✅ La tabla existe<br>";
    
    // Verificar estructura
    $result2 = $conn->query("DESCRIBE concepto_items");
    echo "Estructura:<br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
    while ($row = $result2->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Contar registros
    $result3 = $conn->query("SELECT COUNT(*) as total FROM concepto_items");
    $total = $result3->fetch_assoc()['total'];
    echo "<br>Total registros: " . $total . "<br>";
} else {
    echo "❌ La tabla NO existe<br>";
}

// Buscar orden pagada
echo "<h3>2. Buscando orden pagada:</h3>";
$sql = "SELECT oc.id, oc.folio, oc.estado, oc.catalogo_id, cat.nombre_catalogo
        FROM ordenes_compra oc
        LEFT JOIN catalogos cat ON oc.catalogo_id = cat.id
        WHERE oc.estado = 'pagado'
        AND oc.catalogo_id IS NOT NULL
        LIMIT 1";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $orden = $result->fetch_assoc();
    
    echo "✅ Orden encontrada:<br>";
    echo "ID: " . $orden['id'] . "<br>";
    echo "Folio: " . $orden['folio'] . "<br>";
    echo "Estado: " . $orden['estado'] . "<br>";
    echo "Catálogo: " . $orden['nombre_catalogo'] . " (ID: " . $orden['catalogo_id'] . ")<br><br>";
    
    // Verificar items
    echo "<h3>3. Items de la orden:</h3>";
    $sql_items = "SELECT oci.*, c.codigo_concepto, c.nombre_concepto
                  FROM orden_compra_items oci
                  LEFT JOIN conceptos c ON oci.concepto_id = c.id
                  WHERE oci.orden_compra_id = ?";
    
    $stmt = $conn->prepare($sql_items);
    $stmt->bind_param("i", $orden['id']);
    $stmt->execute();
    $items = $stmt->get_result();
    
    echo "Total items: " . $items->num_rows . "<br><br>";
    
    if ($items->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Concepto</th><th>Descripción</th><th>Cantidad</th><th>Precio</th><th>¿Concepto ID?</th></tr>";
        
        $con_concepto = 0;
        $sin_concepto = 0;
        
        while ($item = $items->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $item['id'] . "</td>";
            echo "<td>" . ($item['codigo_concepto'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars(substr($item['descripcion'], 0, 50)) . "</td>";
            echo "<td>" . $item['cantidad'] . "</td>";
            echo "<td>$" . $item['precio_unitario'] . "</td>";
            
            if ($item['concepto_id']) {
                echo "<td style='color: green;'>✅ " . $item['concepto_id'] . "</td>";
                $con_concepto++;
            } else {
                echo "<td style='color: red;'>❌ NULL</td>";
                $sin_concepto++;
            }
            echo "</tr>";
        }
        echo "</table><br>";
        
        echo "Items CON concepto_id: " . $con_concepto . "<br>";
        echo "Items SIN concepto_id: " . $sin_concepto . "<br><br>";
        
        // Probar transferencia manual
        if ($con_concepto > 0) {
            echo "<h3>4. Probando transferencia manual:</h3>";
            
            // Función simple de transferencia
            function transferenciaDirecta($conn, $orden_id, $catalogo_id) {
                $sql = "SELECT oci.* FROM orden_compra_items oci 
                        WHERE oci.orden_compra_id = ? AND oci.concepto_id IS NOT NULL";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $orden_id);
                $stmt->execute();
                $items = $stmt->get_result();
                
                echo "Items a transferir: " . $items->num_rows . "<br>";
                
                $transferidos = 0;
                while ($item = $items->fetch_assoc()) {
                    echo "Item ID " . $item['id'] . ": ";
                    
                    // Verificar si ya existe
                    $check = "SELECT id FROM concepto_items WHERE orden_compra_item_id = ?";
                    $stmt_check = $conn->prepare($check);
                    $stmt_check->bind_param("i", $item['id']);
                    $stmt_check->execute();
                    
                    if ($stmt_check->get_result()->num_rows == 0) {
                        // Insertar directamente
                        $insert = "INSERT INTO concepto_items 
                                  (concepto_id, orden_compra_item_id, catalogo_id, 
                                   descripcion, cantidad, unidad_medida, precio_unitario, 
                                   subtotal) 
                                  VALUES (?, ?, ?, ?, ?, '', ?, ?)";
                        
                        $stmt_insert = $conn->prepare($insert);
                        $stmt_insert->bind_param("iiisidd",
                            $item['concepto_id'],
                            $item['id'],
                            $catalogo_id,
                            $item['descripcion'],
                            $item['cantidad'],
                            $item['precio_unitario'],
                            $item['subtotal']
                        );
                        
                        if ($stmt_insert->execute()) {
                            echo "✅ Transferido (ID: " . $stmt_insert->insert_id . ")<br>";
                            $transferidos++;
                        } else {
                            echo "❌ Error: " . $stmt_insert->error . "<br>";
                        }
                    } else {
                        echo "⚠️ Ya existe<br>";
                    }
                }
                
                return $transferidos;
            }
            
            $transferidos = transferenciaDirecta($conn, $orden['id'], $orden['catalogo_id']);
            echo "<h4>Total transferidos manualmente: " . $transferidos . "</h4>";
            
        } else {
            echo "<p style='color: red;'>❌ No hay items con concepto_id para transferir</p>";
            echo "<p>Los items deben tener un concepto asignado al crear la orden.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ La orden no tiene items</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ No se encontraron órdenes pagadas</p>";
    echo "<p>Marca una orden como 'pagada' primero.</p>";
}

$conn->close();
?>