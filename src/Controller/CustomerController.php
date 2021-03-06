<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\CustomerRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CustomerController extends AbstractController 
{
    public function __construct(
        SerializerInterface $serializer,
        TagAwareCacheInterface $cachePool,
        EntityManagerInterface $entityManager,
        CustomerRepository $customerRepository,
        UserRepository $userRepository
        )
    {
        $this->serializer = $serializer;
        $this->cachePool = $cachePool;
        $this->entityManager = $entityManager;
        $this->customerRepository = $customerRepository;
        $this->userRepository = $userRepository;
    }

    #[Route('/api/customers/{id}/users', name: 'allUsersToOneCustomer', methods:['GET'])]
    public function getAllUserstoOneCustomer(Request $request, int $id): JsonResponse
    {

        $customer = $this->customerRepository->find($id);

        if($customer === null) {
            return new JsonResponse(['message'=>'This customer not exist'],Response::HTTP_NOT_FOUND);

        } else {

            $page = $request->get('page', 1);
            $limit = $request->get('limit', 3);
    
            $idCache = "getAllUsers-" . $page . "-" . $limit;
            $userList = $this->cachePool->get($idCache, function (ItemInterface $item) use ($page, $limit, $id) {
                echo ("L'ELEMENT N'EST PAS ENCORE EN CACHE !\n");
                $item->tag("usersCache");
                return $this->userRepository->findAllWithPagination($page, $limit, $id); 
            });
    
            $context = SerializationContext::create()->setGroups(["getUsers", "getCustomers", "getAddress"]);
            $jsonUsersList = $this->serializer->serialize($userList, 'json', $context );
            return new JsonResponse($jsonUsersList , Response::HTTP_OK, [], true);
        }
    }

    #[Route('api/customers/{id}/users/{userId}', name: 'detailUserToOneCustomer', methods:['GET'])]
    public function getDetailUsertoOneCustomer(int $id, int $userId): JsonResponse
    {
 
        $customer = $this->customerRepository->find($id);
        $user = $this->userRepository->find($userId);

        if($customer === null) {
            return new JsonResponse(['message'=>'This customer not exist'],Response::HTTP_NOT_FOUND);

        } else if($user === null) { 

        return new JsonResponse(['message'=>'This user not exist'],Response::HTTP_NOT_FOUND);

        } else {

        $idCache = "getOneUser-" . $userId;

        $user = $this->cachePool->get($idCache, function (ItemInterface $item) use($userId, $user) {
            echo ("L'ELEMENT N'EST PAS ENCORE EN CACHE !\n");
            $item->tag("oneUserCache-".$userId);
            return $this->userRepository->find($userId); 
        });

        $context = SerializationContext::create()->setGroups(["getUsers", "getCustomers", "getAddress"]);
        $jsonDetailUserCache = $this->serializer->serialize($user, 'json', $context );
        return new JsonResponse($jsonDetailUserCache , Response::HTTP_OK, [], true);

        }

    }

    #[Route('api/customers/{id}/users', name: 'postUserToOneCustomer', methods:['POST'])]
    public function createUsertoOneCustomer(
        int $id, 
        Request $request, 
        UrlGeneratorInterface $urlGenerator): JsonResponse
    {


        $customer = $this->customerRepository->find($id);

        if($customer === null) {
            return new JsonResponse(['message'=>'This customer not exist'],Response::HTTP_NOT_FOUND);

        } else {
            // transform the json data on object
            //Deserialization
            $contextDeserialization = DeserializationContext::create()->setGroups(["postUsers"]);
            $newUser = $this->serializer->deserialize($request->getContent(), User::class, 'json', $contextDeserialization); 

            $newUser->setCustomer($customer);
            $newUser->setCreatedAt(new \DateTime());
            $newUser->setUpdatedAt(new \DateTime());
            $this->entityManager->persist($newUser);
            $this->entityManager->flush();

            //return response json of user created
            //Serialization
            $contextSerialization = SerializationContext::create()->setGroups(["getUsers"]);
            $jsonNewUser = $this->serializer->serialize($newUser,'json', $contextSerialization );
            $location = $urlGenerator->generate('detailUserToOneCustomer', ['id'=>$customer->getId(), 'userId'=>$newUser->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
            return new JsonResponse($jsonNewUser, Response::HTTP_CREATED, ["Location" => $location], true);

        }
    }


    #[Route('api/customers/{id}/users/{userId}', name: 'deleteUserToOneCustomer', methods:['DELETE'])]
    public function deleteUser( EntityManagerInterface $em, int $id, int $userId): JsonResponse
    {

        $customer = $this->customerRepository->find($id);
        $user = $this->userRepository->find($userId);

        if($customer === null) {

            return new JsonResponse(['message'=>'This customer not exist'],Response::HTTP_NOT_FOUND);

        } else if($user === null) { 

            return new JsonResponse(['message'=>'This user not exist'],Response::HTTP_NOT_FOUND);
 
        } else {
            try {
                $this->cachePool->invalidateTags(["usersCache"]);
                $this->entityManager->remove($user);
                $this->entityManager->flush();

                return new JsonResponse(null, Response::HTTP_NO_CONTENT);
            } catch (Exception $e) {
                dump($e);
            }

        } 

    }
}
