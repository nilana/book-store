<?php

namespace App\Controller;

use App\Repository\BookRepository as RepositoryBookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\BookRepository;
use App\Repository\BookCategoryRepository;
use App\Repository\ShoppingCartRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Book;
use Symfony\Component\HttpFoundation\Cookie; 
use Symfony\Component\HttpFoundation\Response;
use App\Entity\ShoppingCart;

class DefaultController extends AbstractController
{
    protected $orderCookieName = 'order_id';
    /**
     * @Route("/default", name="default")
     */
    public function index(RepositoryBookRepository $bookRepository, BookCategoryRepository $bookCategoryRepository, Request $request, ShoppingCartRepository $cartRepo)
    {
        $category = $request->query->get('category');
        if(!empty($category)){
            $books = $bookRepository->findBy(['category' => $category]);
        }else{
            $books = $bookRepository->findAll();
        }
        $categories = $bookCategoryRepository
                        ->findAll();
        $url = $this->generateUrl('index');
        $totals = ['total_after_discount' => 0];
        if(!empty($request->cookies->get($this->orderCookieName))){
            $orderId = $request->cookies->get($this->orderCookieName);
            $cartItems = $cartRepo->findBy(['order_id' => $orderId]);
            $totals = $this->calculateTotal( $cartItems);
        }
        

        return $this->render('default/index.html.twig', [
            'books' => $books,
            'categories' => $categories,
            'url' => $url,
            'selected_category' => $category,
            'mycart_url' => $this->generateUrl('mycart'),
            'totals' => $totals,
        ]);
    }

    public function buy(Book $book)
    {

        return $this->render('default/buy.html.twig', [
            'book' => $book,
            'cart_url' => $this->generateUrl('add-to-cart'),
            'back_url' => $this->generateUrl('index'),
        ]);
    }

    public function addToCart(Request $request, ShoppingCartRepository $cartRepo, BookRepository $bookRepository) {

        $return = [];
        $data = $request->getContent();
        $data = json_decode($data, true);
        $response = new Response();
        if(!empty($data['quantity'])) {
            try{
                $cookieName = $this->orderCookieName;
                if(!empty($request->cookies->get($cookieName))){//order cookieFound
                    $orderId = $request->cookies->get($cookieName);
                }else{
                    //create order cookie
                    $orderId = uuid_create(UUID_TYPE_RANDOM);
                    $cookie = new Cookie($cookieName, $orderId, time()+(60*60*24*360), '/');
                    $response->headers->setCookie($cookie);
                }
                
                

                $entityManager = $this->getDoctrine()->getManager();
                $cart = new ShoppingCart();
                $book = $bookRepository->find($data['book_id']);
                $cart->setOrderId($orderId);
                $cart->setItem($book);
                $cart->setQuantity($data['quantity']);
                $cart->setDate(new \DateTime());
                $entityManager->persist($cart);
                $entityManager->flush();

                $return = [
                    'status' => true,
                    'message' => 'item added to cart',
                    'order_id' => $orderId,
                    'book_id' => $data['book_id'],
                    'quantity' => $data['quantity'],
                ];
                
            }catch(\Exception $e) {
                $return = [
                    'status' => false,
                    'message' => 'Error occurred',
                ];
            }
        }else{
            $return = [
                'status' => false,
                'message' => 'Please provide the quantity',
            ];
        }
        $response->setContent(json_encode($return));


        $response->send();
        //$this->getResponse()->setCookie('mycookie', 25, time()+60*60*24*360);
        //return $this->json(['username' => 'jane.doe', 'Cookie' => $request->cookies->get('myCookie1')]);
        
    }

    public function myCart(Request $request, ShoppingCartRepository $cartRepo)
    {
        $orderId = $request->cookies->get($this->orderCookieName);
        $items = $cartRepo->findBy(['order_id' => $orderId]);
        $totals = $this->calculateTotal($items);
        
        return $this->render('default/mycart.html.twig', [
            'order_id' => $orderId,
            'items' => $items,
            'back_url' => $this->generateUrl('index'),
            'remove_url' => '/', //$this->generateUrl('remove'),
            'checkout_url' => $this->generateUrl('invoice', ['order_id' => $orderId]),
            'totals' => $totals,
        ]);
    }

    public function remove($id, ShoppingCartRepository $cartRepo, RepositoryBookRepository $bookRepo)
    {
        $cartItem = $cartRepo->find($id);
        $book = $bookRepo->find($cartItem->getItem());
        return $this->render('default/remove.html.twig', [
            'book' => $book,
            'cart_id' => $id,
            'cart_url' => $this->generateUrl('remove-from-cart'),
            'back_url' => $this->generateUrl('mycart'),
        ]);
    }

    public function removeFromCart(Request $request, ShoppingCartRepository $cartRepo)
    {
        try{
            $data = $request->getContent();
            $data = json_decode($data, true);
            $cartItem = $cartRepo->find($data['cart_id']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($cartItem);
            $entityManager->flush();
        }catch(\Exception $e){
            $return = [
                'status' => false,
                'message' => 'Sorry, Error occurred',
            ];
        }
        $return = [
            'status' => true,
            'message' => 'Item removed from cart',
        ];
        return $this->json($return);
    }

    public function invoice(Request $request, ShoppingCartRepository $cartRepo)
    {
        $orderId = $request->query->get('order_id');
        $cartItems = $cartRepo->findBy(['order_id' => $orderId]);
        $totals = $this->calculateTotal($cartItems);

        return $this->render('default/invoice.html.twig', [  
                'back_url' => $this->generateUrl('mycart'),
                'items' => $cartItems,
                'totals' => $totals,
              
        ]);
    }

    protected function calculateTotal($cartItems)
    {

        //discount logic goes here
        
        //generate totals category wise
        $categoryTotals = [
                            'cats' => [
                                'Children' => [
                                    'total' => 0,
                                    'count' => 0,
                                    'discount' => 0,
                                    'total_after_discount' => 0,
                                ],
                                
                                'Fiction' => [
                                    'total' => 0,
                                    'count' => 0
                                ],
                            ],
                            'total' => 0,
                            'discount' => 0,
                            'total_after_discount' => 0,

                        ];
        foreach($cartItems as $cartItem){
            $categoryTotals['cats'][$cartItem->getItem()->getCategory()->getName()]['total'] = $categoryTotals['cats'][$cartItem->getItem()->getCategory()->getName()]['total'] 
            + $cartItem->getItem()->getPrice() * $cartItem->getQuantity();
            $categoryTotals['cats'][$cartItem->getItem()->getCategory()->getName()]['count'] = $categoryTotals['cats'][$cartItem->getItem()->getCategory()->getName()]['count'] 
            + $cartItem->getQuantity();
        }

        $categoryTotals['total_display'] = $categoryTotals['cats']['Children']['total'] + $categoryTotals['cats']['Fiction']['total'];
        $categoryTotals['total'] = $categoryTotals['cats']['Children']['total'] + $categoryTotals['cats']['Fiction']['total'];
        $categoryTotals['total_after_discount'] = $categoryTotals['total'];
        $categoryTotals['cats']['Children']['total_after_discount'] = $categoryTotals['cats']['Children']['total'];

        if($categoryTotals['cats']['Children']['count'] >= 5){
            $categoryTotals['cats']['Children']['discount'] = 10;
            $categoryTotals['cats']['Children']['total_after_discount'] = $categoryTotals['cats']['Children']['total'] * 0.9;
            $categoryTotals['total_after_discount'] = $categoryTotals['cats']['Children']['total_after_discount'] + $categoryTotals['cats']['Fiction']['total'];

        }
        if($categoryTotals['cats']['Children']['count'] >= 10 && $categoryTotals['cats']['Fiction']['count'] >= 10){
            $categoryTotals['discount'] = 5;
            $categoryTotals['total'] = $categoryTotals['cats']['Children']['total_after_discount'] + $categoryTotals['cats']['Fiction']['total'];
            $categoryTotals['total_after_discount'] = $categoryTotals['total'] * 0.95;
        }
        return $categoryTotals;
        //var_dump($categoryTotals);
        //exit;
    }
}
