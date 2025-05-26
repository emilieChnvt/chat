<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Profile;
use App\Entity\User;
use App\Form\MessageForm;
use App\Repository\ConversationRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\MercureJWTGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

final class ConversationController extends AbstractController
{
    #[Route('/', name: 'app_conversations')]
    public function index(ProfileRepository $profileRepository): Response
    {
        if(!$this->getUser()){return $this->redirectToRoute('app_login');}

        return $this->render('conversation/index.html.twig', [
            'profiles' => $profileRepository->findAll(),
        ]);
    }
    #[Route('/conversation/openwith/{id}', name: 'app_conversation_openwith')]
    public function openwith(Profile $profile, ConversationRepository $conversationRepository, EntityManagerInterface $manager): Response
    {
        if(!$this->getUser()){return $this->redirectToRoute('app_login');}
        if(!$profile){return $this->redirectToRoute('conversations');}

        $conversation = $conversationRepository->findOneByCouple($profile, $this->getUser());

        if(!$conversation){
            $conversation = new Conversation();
            $conversation->addParticipant($this->getUser()->getProfile());
            $conversation->addParticipant($profile);
            $manager->persist($conversation);
            $manager->flush();
            $idConversation = $conversation->getId();
        }else{$idConversation = $conversation->getId();}

        return $this->redirectToRoute('app_conversation_open', [
            "id"=>$idConversation,
        ]);
    }

    #[Route('/conversation/open/{id}', name: 'app_conversation_open')]
    public function open(
        Conversation $conversation,
        Request $request,
        EntityManagerInterface $manager,
        HubInterface $hub,
        MercureJWTGenerator $jwtGenerator
    ): Response
    {
        if(!$this->getUser()){return $this->redirectToRoute('app_login');}
        if(!$conversation){return $this->redirectToRoute('conversations');}

        $message = new Message();
        $form = $this->createForm(MessageForm::class, $message);
        $emptyForm = clone $form;
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $message->setCreateAt(new \DateTimeImmutable());
            $message->setAuthor($this->getUser()->getProfile());
            $message->setConversation($conversation);
            $manager->persist($message);
            $manager->flush();

            $update = new Update(
                topics: "conversations/".$conversation->getId(),
                data: $this->renderView('message/stream.html.twig', [
                    "message"=>$message,
                ]),
                private: true
            );

            $hub->publish($update);
            $form = $emptyForm;
        }


        $response = $this->render('conversation/open.html.twig', [
            'conversation' => $conversation,
            'form' => $form,
        ]);
        $jwt = $jwtGenerator->generate($this->getUser()->getProfile());
        $hubUrl = $hub->getPublicUrl();
        $response->headers->set(key:'set-cookie',
            values:"mercureAuthorization=$jwt;
                Path=$hubUrl; HttpOnly;"
        );


        return $response;

    }

}