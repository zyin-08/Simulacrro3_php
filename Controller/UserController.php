<?php

session_start();
$user = new UserController();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        $user->login();
    }
    if (isset($_POST['logout'])) {
        $user->logout();
    }
    if (isset($_POST['register'])) {
        $user->register();
    }
    if (isset($_POST['getDataUser'])) {
        $user->showDataUser();
    }
    if (isset($_POST['update'])) {
        $user->update();
    }
    if (isset($_POST['delete'])) {
        $user->delete();
    }
}

class UserController
{
    private $conn;
    // Constructor de la clase UserController
    public function __construct()
    {
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "daemgame";

        try {
            $this->conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Failure to Connect to DataBase'];
        }
    }

    public function login(): void
{
    if (!isset($_POST["email"], $_POST["password"])) {
        $_SESSION['loggin'] = false;
        $_SESSION['failMessage'] = [
            'message' => "Email or password not provided.",
            'context' => 'Failure to Login In'
        ];
        header("Location: ../View/Login.php");
        exit();
    }

    $mail = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $this->conn->prepare("
        SELECT Administrador, Contrasenya 
        FROM Usuario 
        WHERE email = :mail
    ");
    $stmt->bindParam(':mail', $mail, PDO::PARAM_STR);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        $admin = $user["Administrador"];
        $hashPassword = $user["Contrasenya"];

        // Password hashed correctly
        if (password_verify($password, $hashPassword)) {
            $this->successLogin($mail, $admin);
            return;
        }

        // Legacy plaintext password
        if ($hashPassword === $password) {
            $this->updatePasswordsToHash($password, $mail);
            $this->successLogin($mail, $admin);
            return;
        }

        // Wrong password
        $_SESSION['loggin'] = false;
        $_SESSION['failMessage'] = [
            'message' => "Incorrect email or password.",
            'context' => 'Failure to Login In'
        ];
        header("Location: ../View/Login.php");
        exit();
    }

    // No such user
    $_SESSION['loggin'] = false;
    $_SESSION['failMessage'] = [
        'message' => "Incorrect email or password.",
        'context' => 'Failure to Login In'
    ];
    header("Location: ../View/Login.php");
    exit();
}


    public function updatePasswordsToHash($password, $mail): void
    {
        // Update password alredy created to hash
        $newHashedPassword = password_hash($password, PASSWORD_DEFAULT);
        try {
            $updateStmt = $this->conn->prepare("UPDATE Usuario SET Password = :hashedPassword WHERE Email = :mail");
            $updateStmt->bindParam(':hashedPassword', $newHashedPassword);
            $updateStmt->bindParam(':mail', $mail);
            $updateStmt->execute();
        } catch (PDOException $e) {
            $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Fail updating password to hash'];
        }
    }

    public function successLogin($mail, $admin): void
    {
        // Declare session variables
        $_SESSION['loggin'] = true;
        $_SESSION['admin'] = $admin;
        $_SESSION['user'] = $mail;

        // Get the icon correct for the user
        $stmt = $this->conn->prepare("SELECT imagen FROM Usuario WHERE email = :mail");
        $stmt->bindParam(':mail', $mail);
        $stmt->execute();
        $icon = $stmt->fetchColumn();
        $_SESSION['icon'] = $icon;

        $this->conn = null;
        header("Location: ../View/UserAccount.php");
        exit();
    }

    public function logout(): void
    {
        // clear variables
        unset($_SESSION['admin']);
        unset($_SESSION['loggin']);
        unset($_SESSION['user']);
        unset($_SESSION['icon']);
        // destroy session
        session_destroy();
        // redirect to login
        header("Location: ../View/home.php");
        exit();
    }

    public function register(): void
    {
        // Array that will save all the error of the functions that validate: name, surname, mail...
        $errors = [];

        $file = "";
        $name = $_POST['name'];
        $surname = $_POST['surname'];
        $mail = $_POST['username'];
        $password = $_POST['password'];
        $admin = isset($_POST['admin']) ? 1 : 0;
        $minumPasswordLength = 4;

        // Check if the name/surname have numbers in the string
        if (!preg_match('/[A-Za-z\s]+/', $name) || !preg_match('/[A-Za-z\s]+/', $surname)) {
            $errors['incorrectType'] = "The name/surname must contain only letters.";
        }

        // Check if the email is already in the database
        $stmt = $this->conn->prepare("SELECT Email FROM Usuario WHERE email = :mail");
        $stmt->bindParam(':mail', $mail);
        $stmt->execute();
        // Check if the query have at least one result
        if ($stmt->fetch()) {
            $errors['incorrectMail'] = "The email is already registered.";
        }

        // Check if the password not have at least one numeric character and one alphabetical character
        if (!preg_match('/^(?=.*[0-9])(?=.*[a-zA-Z])[a-zA-Z0-9]+$/', $password)) {
            $errors['incorrectPassword'] = "The password must have at least one number, one letter, and contain only alphanumeric characters.";
        }
        // Chek if the password is equals or minus of 4 charecteres
        if (strlen($password) <= $minumPasswordLength){
            $errors['incorrectLength'] = "The password must have at least 5 characteres";
        }

        // Checks if the user is admin and if the uploaded file size is not empty
        if ($admin && !empty($_FILES['file']['size'])) {
            $directory = "../View/img/profile-icon/";
            $file = $directory . basename($_FILES["file"]["name"]);
            // Save in a variable the extension of the file uploaded
            $extensionFile = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            // If the extension of the file is not in this array of these data: (jpg, png or jpeg) alerts to the user
            if (!in_array($extensionFile, ["jpg", "png", "jpeg"])) {
                $errors['errorExtension'] = "Incorrect image extension, only jpg, png or jpeg are allowed";
            } else {
                // If there is a problem moving the file to the correct directory, alerts to the user
                if (!move_uploaded_file($_FILES["file"]["tmp_name"], $file)) {
                    $errors['errorUpload'] = "The profile picture could not be uploaded correctly";
                }
            }
        }

        if (!empty($errors)) {
            $_SESSION['failMessage'] = ['message' => implode(', ', $errors), 'context' => 'Failing Creating a New User'];
            //$_SESSION['errors'] = $errors;
            if ($admin) {
                // redirect to CreateAdminAccount
                header("Location: ../View/CreateAdminAccount.php");
                exit();
            } else {
                // redirect to CreateAccount
                header("Location: ../View/CreateAccount.php");
                exit();
            }
        }

        $hashPassword = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $this->conn->prepare("INSERT INTO Usuario (`Nombre`, `Apellido`, `Email`, `Contrasenya`, `Imagen`, `Administrador`) VALUES (:name, :surname, :mail, :password, :icon, :admin)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':surname', $surname);
            $stmt->bindParam(':mail', $mail);
            $stmt->bindParam(':password', $hashPassword);
            $stmt->bindParam(':icon', $file);
            $stmt->bindParam(':admin', $admin);

            $stmt->execute();
            // Check if the query insert any data
            
            if ($stmt->rowCount() > 0) {
                // Create new session that save if it is admin, mail of the account and inform that it is logged
                $_SESSION['loggin'] = true;
                $_SESSION['admin'] = $admin;
                $_SESSION['user'] = $mail;
                $_SESSION['icon'] = $file;
                
                $this->conn = null;
                // redirect to home
                header("Location: ../View/Home.php");
            } else {
                $_SESSION['failMessage'] = ['message' => "Error creating the new user.", 'context' => 'Failing Creating a New User'];
                $this->conn = null;
                if ($admin) {
                    // redirect to CreateAdminAccount
                    header("Location: ../View/CreateAdminAccount.php");
                } else {
                    // redirect to login
                    header("Location: ../View/CreateAccount.php");
                }
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Fail Creating a New User'];
        }
    }

    public function showDataUser(): void
    {
        $mail = $_SESSION['user'];
        $stmt = $this->conn->prepare("SELECT Nombre, Apellido FROM Usuario WHERE Email = :mail");
        $stmt->bindParam(':mail', $mail);
        $stmt->execute();
        $dataUser = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->conn = null;

        header('Content-Type: application/json');
        echo json_encode($dataUser);
    }

    public function update(): void
    {
        $errors = [];

        $mail = $_SESSION['user'];
        $name = $_POST['name'];
        $surname = $_POST['surname'];
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirmPassword'];

        // Check if the name/surname have numbers in the string
        if (!preg_match('/^[A-Za-z\s]+$/', $name) || !preg_match('/^[A-Za-z\s]+$/', $surname)) {
            $errors['incorrectType'] = "The name/surname must contain only letters";
        }
        if (!preg_match('/^(?=.*[0-9])(?=.*[a-zA-Z]).{8,}$/', $password)) {
            $errors['incorrectPassword'] = "The password must have at least one number and one letter, and be 8 or more characters long";
        }
        // If the password is not equal to confirmPassword, report to the user
        if ($password != $confirmPassword) {
            $errors['password'] = "The password does not match";
        }

        if (!empty($errors)) {
            $_SESSION['failMessage'] = ['message' => implode(', ', $errors), 'context' => 'Fail Updating Data User'];
            $this->conn = null;
            // redirect to AccountProfile
            header("Location: ../View/AccountConfiguration.php");
            exit();
        }
        // Save in a variable the result of hash the password sended by the user
        $hashPassword = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $this->conn->prepare("UPDATE USUARIO SET Nombre = :name, Apellido = :surname, Password = :password WHERE Email = :mail");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':surname', $surname);
            $stmt->bindParam(':password', $hashPassword);
            $stmt->bindParam(':mail', $mail);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $_SESSION['correctMessage'] = ['message' => "Data user: changes made", 'context' => 'Updated Data User'];
                $this->conn = null;
                // redirect to AccountProfile
                header("Location: ../View/AccountConfiguration.php");
                exit();
            } else {
                $_SESSION['failMessage'] = ['message' => "Fail updating data user", 'context' => 'Fail Updating Data User'];
                $this->conn = null;
                // redirect to AccountProfile
                header("Location: ../View/AccountConfiguration.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Fail Updating Data User'];
        }
    }

    public function delete(): void
    {
        $mail = $_SESSION['user'];
        try {
            $stmt = $this->conn->prepare("DELETE FROM USUARIO WHERE Email = :mail");
            $stmt->bindParam(':mail', $mail);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $this->conn = null;
                $this->logout();
            } else {
                $_SESSION['failMessage'] = ['message' => "The user account could not be deleted", 'context' => 'Fail Deleting User Account'];
                $this->conn = null;
                // redirect to AccountProfile
                header("Location: ../View/AccountConfiguration.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Fail Deleting User Accont'];
        }
    }
}
