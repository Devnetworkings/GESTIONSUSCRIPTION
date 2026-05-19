<?php 

?>

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Panel de control </title>
    <link rel="stylesheet" href="dashboard.css" />
</head>
<body>
    <div id="header">
        <a href="index.php"><button> Logout</button></a>
        <input type="text" id="searchbar" placeholder="Buscar usuario..."/>
        <p>Operador 1 </p>
    </div>
    <div id="areas">
        <nav class="tabs-navigation">
    <button class="tab-btn " data-tab="clientes">Clientes</button>
    <button class="tab-btn" data-tab="pagos">Historial de Pagos</button>
    <button class="tab-btn" data-tab="config">Configuración</button>
</nav>
    </div>
    <div id="content">
      
        </table>
    </div>

</body>
</html>