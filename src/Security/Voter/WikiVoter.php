<?php

namespace App\Security\Voter;

use App\Entity\WikiAccess;
use App\Entity\WikiAccessInterface;
use App\Entity\WikiArticle;
use App\Entity\WikiCategory;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class WikiVoter extends Voter {

    const VIEW = 'view';
    const ADD = 'add';
    const EDIT = 'edit';
    const REMOVE = 'remove';

    private $decisionManager;

    public function __construct(AccessDecisionManagerInterface $decisionManager) {
        $this->decisionManager = $decisionManager;
    }

    /**
     * @inheritDoc
     */
    protected function supports($attribute, $subject) {
        $attributes = [
            static::VIEW,
            static::ADD,
            static::EDIT,
            static::REMOVE
        ];

        if(!in_array($attribute, $attributes)) {
            return false;
        }

        return $subject instanceof WikiAccessInterface || $subject === null;
    }

    /**
     * @inheritDoc
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token) {
        switch ($attribute) {
            case static::VIEW:
                return $this->canView($subject, $token);

            case static::ADD:
            case static::EDIT:
            case static::REMOVE:
                return $this->canAddOrEditOrRemove($subject, $token);
        }

        throw new \LogicException('This code should not be reached');
    }

    private function canView(?WikiAccessInterface $wikiCategoryOrArticle, TokenInterface $token) {
        if($wikiCategoryOrArticle === null) {
            // Everyone can see root level
            return true;
        }

        /*
         * Simply walk through the tree of articles/categories
         * and check the permissions.
         */
        while($wikiCategoryOrArticle !== null) {
            if(WikiAccess::Inherit()->equals($wikiCategoryOrArticle->getAccess()) !== true) {
                if($this->decisionManager->decide($token, $this->getRolesForAccess($wikiCategoryOrArticle->getAccess())) !== true) {
                    return false;
                }
            }

            if($wikiCategoryOrArticle instanceof WikiArticle) {
                $wikiCategoryOrArticle = $wikiCategoryOrArticle->getCategory();
            } else if($wikiCategoryOrArticle instanceof WikiCategory) {
                $wikiCategoryOrArticle = $wikiCategoryOrArticle->getParent();
            } else {
                throw new \LogicException(sprintf('You must specify logic for retrieving a parent for class %s', get_class($wikiCategoryOrArticle)));
            }
        }

        return true;
    }

    private function canAddOrEditOrRemove(?WikiAccessInterface $wikiAccess, TokenInterface $token) {
        if($this->decisionManager->decide($token, ['ROLE_ADMIN']) !== true) {
            // user must have at least ROLE_ADMIN
            return false;
        }

        // user must have permission to view the article
        return $this->canView($wikiAccess, $token);
    }

    private function getRolesForAccess(WikiAccess $access) {
        if(WikiAccess::All()->equals($access)) {
            return ['ROLE_USER'];
        } if(WikiAccess::Admin()->equals($access)) {
            return ['ROLE_ADMIN'];
        } else if(WikiAccess::SuperAdmin()->equals($access)) {
            return ['ROLE_SUPER_ADMIN'];
        }

        return [ ];
    }
}