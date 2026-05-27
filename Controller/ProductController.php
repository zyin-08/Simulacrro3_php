<?php

session_start();


$product = new ProductController();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Create a new product
    if (isset($_POST['createNewProduct'])) {
        $product->createProduct();
    }
    // Update a product
    if (isset($_POST['updateProduct'])) {
        $product->updateProduct();
    }
    // Get if there's a platform avaible to add a product
    if (isset($_POST['getAvaiblePlatforms'])) {
        $idProduct = $_POST['getAvaiblePlatforms'];
        $product->getAvailablePlatforms($idProduct);
    }
    // Add a new Platform to a Product already Created
    if (isset($_POST['addProductPlatform'])) {
        $product->addProductPlatform();
    }
    // Delete the product that admin selected in the popUp
    if (isset($_POST['deleteProduct'])) {
        $product->deleteProduct();
    }
}

class ProductController
{
    private $conn;
    // Constructor of the class ProductController
    public function __construct()
    {
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "mp0487_daemgame";

        try {
            $this->conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Failure to Connect to DataBase'];
        }
    }

    public function getIdPlatform($platform)
    {
        $stmt = $this->conn->prepare("SELECT Id FROM PLATAFORMA WHERE Nombre = :name");
        $stmt->bindParam(':name', $platform);
        $stmt->execute();
        $idPlatform = $stmt->fetchColumn();

        return $idPlatform;
    }

    public function getPathDirectory($platform)
    {
        $directory = "";

        switch ($platform) {
            case "Pc":
                $directory = "../View/img/products/pc/";
                break;
            case "Xbox":
                $directory = "../View/img/products/xbox/";
                break;
            case "Playstation":
                $directory = "../View/img/products/playstation/";
                break;
            case "Nintendo":
                $directory = "../View/img/products/nintendo/";
                break;
        }
        return $directory;
    }

    public function validatePrice($price)
    {
        $price = trim($price);
        // Check if the $price only have numbers or have a ,
        if (preg_match('/^[0-9,.]+$/', $price)) {
            // Replace the , by .
            $correctTypePrice = str_replace(',', '.', $price);
            return $correctTypePrice;
        } else {
            return false;
        }
    }

    public function isAlredyCreated($productName)
    {
        $stmt = $this->conn->prepare("Select idProducto FROM PRoducto Where Nombre = :name");
        $stmt->bindParam(':name', $productName);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function createProduct(): void
    {
        $errors = [];

        $platform = $_POST['type'];
        $productName = $_POST['newProductName'];
        $productDescription = $_POST['newDescription'];
        $productStock = $_POST['newStock'];
        $productOfferPrice = $_POST['newDiscount'];
        $productPrice = $_POST['newTotalPrice'];

        $idPlatform = $this->getIdPlatform($platform);
        $correctTypePrice = $this->validatePrice($productPrice);
        $isAlredyCreated = $this->isAlredyCreated($productName);

        // Check if the user send a correct type for the input Total Price
        if (!$correctTypePrice) {
            $errors['incorrectType'] = "The product price only allows numerical characters or commas";
        }
        // Check if the name of the product send by the user is alredy created in the database
        if ($isAlredyCreated) {
            $errors['isAlredyCreated'] = "The product is alredy created";
        }
        // Check if the image is corerctly upload
        $resultUploadImage = $this->uploadProductImage($platform, "imagesGames");
        // If there are errors in image upload, concatenate them into a single string
        if (!empty($resultUploadImage['errors'])) {
            $errors[] = implode(', ', $resultUploadImage['errors']);
        }

        if (!empty($errors)) {
            $_SESSION['failMessage'] = ['message' => implode(', ', $errors), 'context' => 'Fail Creating a new Product'];
            header("Location: ../View/AccountConfiguration.php");
            exit();
        } else {
            try {
                // Prepare the sql to instert data
                $sqlInsert = "INSERT INTO PRODUCTO (`Nombre`, `Descripcion`, `Stock`, `Precio`, `Oferta`) 
                VALUES (:name , :description, :stock, :price, :offer)";
                $stmt = $this->conn->prepare($sqlInsert);
                $stmt->bindParam(':name', $productName);
                $stmt->bindParam(':description', $productDescription);
                $stmt->bindParam(':stock', $productStock);
                $stmt->bindParam(':price', $productPrice);
                $stmt->bindParam(':offer', $productOfferPrice);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    // If the data is correct insert, save in a variable the last id of the product
                    $idProduct = $this->conn->lastInsertId();
                    // Call to the method createProductInPlatform and pass paramether
                    $this->createProductInPlatform($idProduct, $idPlatform, $resultUploadImage);
                } else {
                    $_SESSION['failMessage'] = ['message' => "An error has occurred during product creation", 'context' => 'Fail Creating a new Product'];
                    header("Location: ../View/AccountConfiguration.php");
                }
            } catch (PDOException $e) {
                $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Fail Message'];
            }

            $this->conn = null;
            exit();
        }
    }


    public function getTotalProductTrend()
    {
        $stmt = $this->conn->prepare("SELECT COUNT(Tendencia) AS TotalTrend FROM producto_plataforma WHERE Tendencia = 1;");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['TotalTrend'];
        $this->conn = null;
    }

    public function createProductInPlatform($idProduct, $idPlatform, $resultUploadImage): void
    {
        $filePath = $resultUploadImage['file'];

        $totalProductsTrend = $this->getTotalProductTrend();
        $limitTotalProductsTrend = 18;
        if ($totalProductsTrend <  $limitTotalProductsTrend) {
            $trend = rand(0, 1);
        } else {
            $trend = 0;
        }

        try {
            $stmt = $this->conn->prepare("INSERT INTO producto_plataforma (`id_producto`, `id_plataforma`, `Imagen`, `Tendencia`) VALUES (:idProduct, :idPlatform, :image, :trend)");
            $stmt->bindParam(':idProduct', $idProduct);
            $stmt->bindParam(':idPlatform', $idPlatform);
            $stmt->bindParam(':image', $filePath);
            $stmt->bindParam(':trend', $trend);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $_SESSION['correctMessage'] = ['message' => "New Product created correctly", 'context' => 'Created Product'];
                header("Location: ../View/AccountConfiguration.php");
            } else {
                $_SESSION['failMessage'] = ['message' => "An error has occurred during product platform creation", 'context' => 'Fail Creating a new Product'];
                header("Location: ../View/AccountConfiguration.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Fail Message'];
            header("Location: ../View/AccountConfiguration.php");
            exit();
        }

        $this->conn = null;
    }

    public function uploadProductImage($platform, $fileInputName)
    {
        $directory = $this->getPathDirectory($platform);
        $errors = [];

        $file = $directory . basename($_FILES[$fileInputName]["name"]);
        $extensionFile = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        // Check if the file upload Ok
        if ($_FILES[$fileInputName]["error"] == UPLOAD_ERR_OK) {
            // If the extension of the file is not in this array of these data: (jpg, png or jpeg) alerts to the user
            if (!in_array($extensionFile, ["jpg", "png", "jpeg"])) {
                $errors['errorExtension'] = "ERROR: Incorrect image extension, only jpg, png or jpeg is allowed";
            } else {
                // If there is a problem switching the file to the correct directory alerts to the user
                if (!move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $file)) {
                    $errors['errorUpload'] = "ERROR: The profile picture could not be uploaded correctly";
                }
            }
        } else {
            $errors['uploadError'] = "ERROR: An error occurred while loading the file";
        }
        return ['file' => $file, 'errors' => $errors];
        $this->conn = null;
    }


    public function updateProduct(): void
    {
        $idProduct = $_POST['productId'];
        $productName = $_POST['productName'];
        $productDescription = $_POST['description'];
        $productStock = $_POST['stock'];
        $productDiscount = $_POST['discount'];
        $productPrice = $_POST['totalPrice'];

        $correctTypePrice = $this->validatePrice($productPrice);
        // Check if the user send a correct type for the input Total Price
        if (!$correctTypePrice) {
            $errors['incorrectType'] = "Incorrect data type for product price: only allows numerical characters or commas";
        }
        if (!empty($errors)) {
            $_SESSION['failMessage'] = ['message' => implode(', ', $errors), 'context' => 'Fail Creating a new Product'];
            header("Location: ../View/AccountConfiguration.php");
            exit();
        } else {
            try {
                $updateProductQuery = "UPDATE Producto SET Nombre = :name, Descripcion = :description, Stock = :stock, Oferta = :offert, Precio = :price WHERE idProducto = :idProduct";
                $stmt = $this->conn->prepare($updateProductQuery);
                $stmt->bindParam(':name', $productName);
                $stmt->bindParam(':description', $productDescription);
                $stmt->bindParam(':stock', $productStock);
                $stmt->bindParam(':offert', $productDiscount);
                $stmt->bindParam(':price', $productPrice);
                $stmt->bindParam(':idProduct', $idProduct);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $_SESSION['correctMessage'] = ['message' => "Product updated successfully", 'context' => 'Updated Data Product'];
                } else {
                    $_SESSION['failMessage'] = ['message' => "An error occurred while trying to update the product information", 'context' => 'Updating Data Product'];
                }
            } catch (PDOException $e) {
                $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Fail Message'];
            }
        }

        header("Location: ../View/AccountConfiguration.php");
        $this->conn = null;
        exit();
    }

    public function getAvailablePlatforms($idProduct)
    {
        $sql = "SELECT pl.nombre FROM plataforma pl WHERE pl.id NOT IN( SELECT pp.id_plataforma FROM producto_plataforma pp WHERE pp.id_producto = :idProduct)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':idProduct', $idProduct);
        $stmt->execute();

        $platforms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($platforms);

        $this->conn = null;
    }

    public function addProductPlatform(): void
    {
        $failMessage = [];
        $idProduct = $_POST['productName'];
        $platform = $_POST['platform'];
        // Call the method getIdPlatform to know what id is the platform selected by the user
        $idPlatform = $this->getIdPlatform($platform);
        // Call the method uploadProductImage to return the path of the image or the error
        $resultUploadImage = $this->uploadProductImage($platform, "platformImage");

        $failMessage = $resultUploadImage['errors'];
        if (!empty($failMessage)) {
            $_SESSION['failMessage'] = ['message' => implode(', ', $failMessage), 'context' => 'Adding new Plaftorm for a Product'];
            header("Location: ../View/AccountConfiguration.php");
            exit();
        } else {
            // Save in the variable file the path of the image
            $file = $resultUploadImage['file'];
        }
        // Call the method getTotalProductTrend to know the total of producto_plataforma in trend
        $totalProductsTrend = $this->getTotalProductTrend();
        $limitTotalProductsTrend = 18;
        // If there are less of 18
        if ($totalProductsTrend <  $limitTotalProductsTrend) {
            $trend = rand(0, 1);
        } else {
            $trend = 0;
        }
        try {
            $stmt = $this->conn->prepare("INSERT INTO Producto_Plataforma (id_producto, id_plataforma, Imagen, Tendencia) VALUES (:idProduct, :idPlatform, :file, :trend)");
            $stmt->bindParam(':idProduct', $idProduct);
            $stmt->bindParam(':idPlatform', $idPlatform);
            $stmt->bindParam(':file', $file);
            $stmt->bindParam(':trend', $trend);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $_SESSION['correctMessage'] = ['message' => "New platform added to the product", 'context' => 'Adding new Platform to a Product'];
            } else {
                $_SESSION['failMessage'] = ['message' =>  "An error occurred while trying to add the new platform to the product", 'context' => 'Adding new Platform for a Product'];
            }
        } catch (PDOException $e) {
            $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Fail Message'];
        }

        header("Location: ../View/AccountConfiguration.php");
        $this->conn = null;
        exit();
    }

    public function deleteProduct(): void
    {
        $idProduct = $_POST['productName'];
        try {
            $stmt = $this->conn->prepare("DELETE FROM Producto WHERE idProducto = :idProduct");
            $stmt->bindParam(':idProduct', $idProduct);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $_SESSION['correctMessage'] = ['message' => "Product deleted", 'context' => 'Deleted Product'];
            } else {
                $_SESSION['failMessage'] = ['message' => "An error occurred while trying to delete the product", 'context' => 'Deleting Product'];
            }
        } catch (PDOException $e) {
            $_SESSION['failMessage'] = ['message' => $e->getMessage(), 'context' => 'Fail Message'];
        }
        header("Location: ../View/AccountConfiguration.php");
        $this->conn = null;
        exit();
    }
}
