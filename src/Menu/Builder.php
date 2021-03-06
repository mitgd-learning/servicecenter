<?php

namespace App\Menu;

use App\Entity\Announcement;
use App\Entity\Problem;
use App\Entity\User;
use App\Repository\AnnouncementRepository;
use App\Repository\AnnouncementRepositoryInterface;
use App\Repository\ProblemRepository;
use App\Repository\ProblemRepositoryInterface;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use LightSaml\SpBundle\Security\Authentication\Token\SamlSpToken;
use SchoolIT\CommonBundle\Helper\DateHelper;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Builder {

    private $factory;
    private $tokenStorage;
    private $announcementRepository;
    private $problemRepository;
    private $authorizationChecker;
    private $dateHelper;
    private $translator;

    private $idpProfileUrl;

    public function __construct(FactoryInterface $factory, TokenStorageInterface $tokenStorage,
                                ProblemRepositoryInterface $problemRepository,
                                AnnouncementRepositoryInterface $announcementRepository,
                                AuthorizationCheckerInterface $authorizationChecker, DateHelper $dateHelper,
                                TranslatorInterface $translator, string $idpProfileUrl) {
        $this->factory = $factory;
        $this->tokenStorage = $tokenStorage;
        $this->announcementRepository = $announcementRepository;
        $this->problemRepository = $problemRepository;
        $this->authorizationChecker = $authorizationChecker;
        $this->dateHelper = $dateHelper;
        $this->translator = $translator;
        $this->idpProfileUrl = $idpProfileUrl;
    }

    public function mainMenu(array $options): ItemInterface {
        $user = $this->tokenStorage
            ->getToken()->getUser();

        $menu = $this->factory->createItem('root')
            ->setChildrenAttribute('class', 'navbar-nav mr-auto');

        $menu->addChild('dashboard.label', [
            'route' => 'dashboard'
        ]);

        if($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $count = $this->problemRepository->countOpen();
        } else {
            $count = $this->problemRepository->countByUser($user);
        }

        $menu->addChild('problems.label', [
            'route' => 'problems'
        ])
            ->setAttribute('count', $count);

        $menu->addChild('status.label', [
            'route' => 'current_status'
        ]);

        $menu->addChild('announcements.label', [
            'route' => 'announcements'
        ])
            ->setAttribute('count', $this->announcementRepository->countActive($this->dateHelper->getToday()));

        $menu->addChild('wiki.label', [
            'route' => 'wiki'
        ]);


        return $menu;
    }

    public function adminMenu(array $options): ItemInterface {
        $root = $this->factory->createItem('root')
            ->setChildrenAttributes([
                'class' => 'navbar-nav float-lg-right'
            ]);

        $menu = $root->addChild('admin', [
            'label' => ''
        ])
            ->setAttribute('icon', 'fa fa-cogs')
            ->setAttribute('title', $this->translator->trans('admin.label'))
            ->setExtra('menu', 'admin')
            ->setExtra('menu-container', '#submenu')
            ->setExtra('pull-right', true);

        if($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $menu->addChild('devices.label', [
                'route' => 'devices'
            ]);
            $menu->addChild('statistics.label', [
                'route' => 'statistics'
            ]);
            $menu->addChild('placards.label', [
                'route' => 'placards'
            ]);
            $menu->addChild('notifications.label', [
                'route' => 'notifications'
            ]);
        }

        if($this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN')) {
            $menu->addChild('admin_announcements', [
                'route' => 'admin_announcements',
                'label' => 'announcements.label'
            ]);
            $menu->addChild('device_types.label', [
                'route' => 'admin_devicetypes'
            ]);
            $menu->addChild('rooms.label', [
                'route' => 'admin_rooms'
            ]);
            $menu->addChild('problem_types.label', [
                'route' => 'admin_problemtypes'
            ]);
            $menu->addChild('logs.label', [
                'route' => 'admin_logs'
            ]);
            $menu->addChild('mails.label', [
                'route' => 'admin_mails'
            ]);
            $menu->addChild('idp_exchange.label', [
                'route' => 'idp_exchange_admin'
            ]);
        }


        return $root;
    }

    public function userMenu(array $options): ItemInterface {
        $menu = $this->factory->createItem('root')
            ->setChildrenAttributes([
                'class' => 'navbar-nav float-lg-right'
            ]);

        $user = $this->tokenStorage->getToken()->getUser();

        if($user === null || !$user instanceof User) {
            return $menu;
        }

        $displayName = $user->getUsername();

        $userMenu = $menu->addChild('user', [
            'label' => $displayName
        ])
            ->setAttribute('icon', 'fa fa-user')
            ->setExtra('menu', 'user')
            ->setExtra('menu-container', '#submenu')
            ->setExtra('pull-right', true);

        $userMenu->addChild('profile.label', [
            'uri' => $this->idpProfileUrl
        ])
            ->setAttribute('target', '_blank');

        $menu->addChild('label.logout', [
            'route' => 'logout',
            'label' => ''
        ])
            ->setAttribute('icon', 'fas fa-sign-out-alt')
            ->setAttribute('title', $this->translator->trans('auth.logout'));

        return $menu;
    }

    public function servicesMenu(): ItemInterface {
        $root = $this->factory->createItem('root')
            ->setChildrenAttributes([
                'class' => 'navbar-nav float-lg-right'
            ]);

        $token = $this->tokenStorage->getToken();

        if($token instanceof SamlSpToken) {
            $menu = $root->addChild('services', [
                'label' => ''
            ])
                ->setAttribute('icon', 'fa fa-th')
                ->setExtra('menu', 'services')
                ->setExtra('pull-right', true)
                ->setAttribute('title', $this->translator->trans('services.label'));

            foreach($token->getAttribute('services') as $service) {
                $menu->addChild($service->name, [
                    'uri' => $service->url
                ])
                    ->setAttribute('title', $service->description)
                    ->setAttribute('target', '_blank');
            }
        }

        return $root;
    }
}