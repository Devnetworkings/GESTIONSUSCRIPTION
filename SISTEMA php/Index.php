<?php
    
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>GESTION LOGIN</title>
<meta charset="utf-8" />
    <link rel="stylesheet" href="loguin.css" />
</head>
<body>
    <div>
        <form action="loginp.php" method="post" id="formu">
            <h3> INICIAR SESIÓN</h3>
            <input type="text" placeholder="OPERADOR" name="operator">
            <input type="password" placeholder="PASSWORD" name="password">
            <input type="submit" id="send"/>

            <div id="create-recovery"> 
            <input type="button" value="C:O" />
            <input type="button" value="R:O" />
            </div>
        </form>

    </div>
    <div id="footer"></div>
</body>


</html>