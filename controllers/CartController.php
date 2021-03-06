<?php
/**
 * Контроллер работы с корзиной {/cart/}
 */

//подключение моделей
include_once '../models/CategoriesModel.php';
include_once '../models/ProductsModel.php';
include_once '../models/OrdersModel.php';
include_once '../models/PurchaseModel.php';

/**
 * Добавление продуктов в корзину
 * @return bool инфор об операции успех, кол-во эл в корзине
 */
function addtocartAction(){
    $itemId = isset($_GET['id']) ? intval($_GET['id']) : null;
    if(!$itemId) return false;

    $resData = array();
    //Если знач. не найдено, добавляем
    if(isset($_SESSION['cart']) && array_search($itemId, $_SESSION['cart']) === false){
        $_SESSION['cart'][] = $itemId;
        $resData['cntItems'] = count($_SESSION['cart']);
        $resData['success'] = 1;
    }else{
        $resData['success'] = 0;
    }
    echo json_encode($resData);
}

/**
 * Удаление продукта из товара
 */
function removefromcartAction(){
    $itemId = isset($_GET['id']) ? intval($_GET['id']) : null;
    if(!$itemId) exit();

    $resData = array();
    $key = array_search($itemId, $_SESSION['cart']);
    if($key !== false){
        unset($_SESSION['cart'][$key]);
        $resData['success'] = 1;
        $resData['cntItems'] = count($_SESSION['cart']);
    }else{
        $resData['success'] = 0;
    }
    echo json_encode($resData);
}

/**
 * Формирование страницы корзины
 * @link /cart/
 */
function indexAction($smarty){
    $itemsIds = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
    $rsCategories = getAllMainCatsWithChildren();
    $rsProducts = getProductsFromArray($itemsIds);

    $smarty->assign('pageTitle', 'Корзина');
    $smarty->assign('rsCategories', $rsCategories);
    $smarty->assign('rsProducts', $rsProducts);

    loadTemplate($smarty, 'header');
    loadTemplate($smarty, 'cart');
    loadTemplate($smarty, 'footer');
}

/**
 * Формирование страницы заказа
 */
function orderAction($smarty){
    //получаем массив ид-ов (ID) продуктов корзины
    $itemsIds = isset($_SESSION['cart']) ? $_SESSION['cart'] : null;
    //если корзина пустая редирект в корзину
    if(!$itemsIds){
        redirect('/cart/');
        return;
    }

    //получаем из массива пост колво покупаемых товаров
    $itemsCnt = array();
    foreach($itemsIds as $item){
        //формируем ключ для массива пост
        $postVar = 'itemCnt_' . $item;
        //создаём элемент массива кол-ва покупаемого товара
        //ключ массив ID товаров значение массива - кол-во товара
        //$itemCnt[1] = 3; товар с ID == 1 покупают 3 штуки
        $itemsCnt[$item] = isset($_POST[$postVar]) ? $_POST[$postVar] : null;
    }
    //получаем список продуктов по массиву корзины
    $rsProducts = getProductsFromArray($itemsIds);

    //добавляем каждому продукту доп. поле
    //realPrice = кол-во продк * на цену продукта
    //cnt = колво покупаемого товара

    //&$item  = для того чтобы при изменении переменно $item
    //менялся и элемент массива $rsProducts
    $i = 0;
    foreach($rsProducts as &$item){
        $item['cnt'] = isset($itemsCnt[$item['id']]) ? $itemsCnt[$item['id']] : 0;
        if($item['cnt']){
            $item['realPrice'] = $item['cnt'] * $item['price'];
        }else{
            //если товар в корзине есть а колв-о == 0
            //то удаляем товар
            unset($rsProducts[$i]);
        }
        $i++; //знать на каком элементе мы находимся
    }

    if(!$rsProducts){
        echo "Корзина пуста";
        return;
    }

    //полученный массив покупаемых товаров помещаем в сессионную переменную
    $_SESSION['saleCart'] = $rsProducts;

    $rsCategories = getAllMainCatsWithChildren();

    //hideLoginBox переменная - флаг для того что бы спрятать блоки логина
    //и регистрации в боковой панели
    if(!isset($_SESSION['user'])){
        $smarty->assign('hideLoginBox', 1);
    }

    $smarty->assign('pageTitle', 'Заказ');
    $smarty->assign('rsCategories', $rsCategories);
    $smarty->assign('rsProducts', $rsProducts);

    loadTemplate($smarty, 'header');
    loadTemplate($smarty, 'order');
    loadTemplate($smarty, 'footer');
}

/**
 * AJAX Функция сохранение заказа
 *
 * @param array $_SESSION['selectCart'] массив покупаемых продуктов
 * @param json информация о результате выполнения
 */
function saveorderAction(){
    //получаем массив покупаемых продуктов
    $cart = isset($_SESSION['saleCart']) ? $_SESSION['saleCart'] : null;
    //если корзина пустая, то формируем ответ с ошибкой, отдаём его в формате
    //json и выходим из функции
    if(!$cart){
        $resData['success'] = 0;
        $resData['message'] = 'Нет товаров для заказа';
        echo json_encode($resData);
        return;
    }

    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    //создаём новый заказ и получаем его ID
    $orderId = makeNewOrder($name, $phone, $address);

    //если заказ не создан то выдаём ошибку и завершаем функцию
    if(!$orderId){
        $resData['success'] = 0;
        $resData['message'] = 'Ошибка создания заказа';
        echo json_encode($resData);
        return;
    }

    //сохраняем товары для созданного заказа
    $res = setPurchaseForOrder($orderId, $cart);

    //если успешно, то формируем ответ, удаляем переменные корзины
    if($res){
        $resData['success'] = 1;
        $resData['message'] = 'Заказ сохранён';
        unset($_SESSION['saleCart']);
        unset($_SESSION['Cart']);
    }else{
        $resData['success'] = 0;
        $resData['message'] = 'Ошибка внесения даннхы для заказа № ' . $orderId;
    }
    echo json_encode($resData);
}