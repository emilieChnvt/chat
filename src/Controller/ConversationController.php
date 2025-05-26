<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Profile;
use App\Form\MessageForm;
use App\Repository\ConversationRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConversationController extends AbstractController
{
    #[Route('/', name: 'app_conversations')]
    public function index(ProfileRepository $profileRepository): Response
    {
        if(!$this->getUser()){
            return $this->redirectToRoute('app_login');
        }

        return $this->render('conversation/index.html.twig', [
            'profiles' => $profileRepository->findAll(),
        ]);
    }

    #[Route('/conversation/openWith{id}', name: 'app_conversation_openwith')]
    public function openWith(Profile $profile, ConversationRepository $conversationRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        if(!$this->getUser()){
            return $this->redirectToRoute('app_login');
        }
        if(!$profile){return $this->redirectToRoute('app_conversations');}

        $conversation = $conversationRepository->findOneBy([$profile , $this->getUser()->getProfile()]);

        if(!$conversation){
            $conversation = new Conversation();
            $conversation->addParticipant($this->getUser());
            $conversation->addParticipant($profile);
            $entityManager->persist($conversation);
            $entityManager->flush();
            $idConv = $conversation->getId();

        }else{
            $idConv = $conversation->getId();
        }
        return $this->redirectToRoute('app_conversation_open', [
            'idConv' => $idConv
        ]);
    }

    #[Route('/conversation/open{id}', name: 'app_conversation_open')]
    public function open(Conversation $conversation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if(!$this->getUser()){return $this->redirectToRoute('app_login');}
        if(!$conversation){return $this->redirectToRoute('app_conversations');}

        $message = new Message();
        $form = $this->createForm(MessageForm::class , $message);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $message->setConversation($conversation);
            $message->setAuthor($this->getUser()->getProfile());
            $message->setCreateAt(new \DateTimeImmutable());
            $entityManager->persist($message);
            $entityManager->flush();

        }

    }
}
