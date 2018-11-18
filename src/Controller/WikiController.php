<?php

namespace App\Controller;

use App\Entity\WikiArticle;
use App\Entity\WikiCategory;
use App\Form\WikiArticleType;
use App\Form\WikiCategoryType;
use App\Helper\Wiki\WikiSearcher;
use App\Repository\WikiArticleRepositoryInterface;
use App\Repository\WikiCategoryRepositoryInterface;
use App\Security\Voter\WikiVoter;
use EasySlugger\SluggerInterface;
use SchoolIT\CommonBundle\Form\ConfirmType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class WikiController extends Controller {

    const WIKI_SEARCH_LIMIT = 25;

    private $slugger;

    public function __construct(SluggerInterface $slugger) {
        $this->slugger = $slugger;
    }

    /**
     * @Route("/wiki", name="wiki")
     * @Route("/wiki/{id}-{slug}", name="wiki_category")
     */
    public function showCategory(WikiCategory $category = null, WikiArticleRepositoryInterface $wikiArticleRepository, WikiCategoryRepositoryInterface $wikiCategoryRepository) {
        $isRootCategory = false;

        if($category === null) {
            $category = (new WikiCategory());

            /** @var WikiCategory[] $categories */
            $categories = $wikiCategoryRepository
                ->findByParent(null);

            foreach($categories as $c) {
                $category->addCategory($c);
            }

            /** @var WikiArticle[] $articles */
            $articles = $wikiArticleRepository
                ->findByCategory(null);

            foreach($articles as $a) {
                $category->addArticle($a);
            }

            $isRootCategory = true;
        }

        $this->denyAccessUnlessGranted(WikiVoter::VIEW, $category);

        return $this->render('wiki/category.html.twig', [
            'category' => $category,
            'isRootCategory' => $isRootCategory
        ]);
    }

    /**
     * @Route("/wiki/a/{id}-{slug}", name="wiki_article")
     */
    public function showArticle(WikiArticle $article) {
        $this->denyAccessUnlessGranted(WikiVoter::VIEW, $article);

        return $this->render('wiki/article.html.twig', [
            'article' => $article
        ]);
    }

    /**
     * @Route("/wiki/search", name="wiki_search")
     */
    public function search(Request $request, WikiSearcher $wikiSearcher) {
        $query = $request->query->get('q', null);

        if(empty($query)) {
            return $this->redirectToRoute('wiki');
        }

        $page = $request->query->get('page', 1);

        if(!is_numeric($page) || $page <= 0) {
            $page = 1;
        }

        $result = $wikiSearcher->search($query, $page, static::WIKI_SEARCH_LIMIT);

        return $this->render('wiki/search_results.html.twig', [
            'result' => $result,
            'q' => $query
        ]);
    }

    /**
     * @Route("/wiki/articles/add", name="add_wiki_root_article")
     * @Route("/wiki/{id}-{slug}/articles/add", name="add_wiki_article")
     */
    public function addArticle(Request $request, WikiCategory $parentCategory = null) {
        $this->denyAccessUnlessGranted(WikiVoter::ADD, $parentCategory);

        $article = (new WikiArticle())
            ->setCategory($parentCategory);
        $form = $this->createForm(WikiArticleType::class, $article, [ ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $this->makeSlug($article);

            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();

            $this->addFlash('success', 'wiki.articles.add.success');

            if($parentCategory === null) {
                return $this->redirectToRoute('wiki');
            }

            return $this->redirectToRoute('wiki_category', [
                'id' => $parentCategory->getId(),
                'slug' => $parentCategory->getSlug()
            ]);
        }

        return $this->render('wiki/articles/add.html.twig', [
            'form' => $form->createView(),
            'article' => $article
        ]);
    }

    /**
     * @Route("/wiki/a/{id}-{slug}/edit", name="edit_wiki_article")
     */
    public function editArticle(Request $request, WikiArticle $article) {
        $this->denyAccessUnlessGranted(WikiVoter::EDIT, $article);

        $form = $this->createForm(WikiArticleType::class, $article, [ ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $this->makeSlug($article);

            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();

            $this->addFlash('success', 'wiki.articles.edit.success');

            return $this->redirectToRoute('wiki_article', [
                'id' => $article->getId(),
                'slug' => $article->getSlug()
            ]);
        }

        return $this->render('wiki/articles/edit.html.twig', [
            'form' => $form->createView(),
            'article' => $article
        ]);
    }

    /**
     * @Route("/wiki/a/{id}-{slug}/remove", name="remove_wiki_article")
     */
    public function removeArticle(Request $request, WikiArticle $article) {
        $this->denyAccessUnlessGranted(WikiVoter::DELETE, $article);

        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(ConfirmType::class, null, [
            'message' => 'wiki.articles.remove.confirm'
        ]);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $em->remove($article);
            $em->flush();

            $this->addFlash('success', 'wiki.articles.remove.success');
            return $this->redirectToRoute('wiki_article', [
                'id' => $article->getId(),
                'slug' => $article->getSlug()
            ]);
        }

        return $this->render('wiki/articles/remove.html.twig', [
            'form' => $form->createView(),
            'article' => $article
        ]);
    }

    /**
     * @Route("/wiki/categories/add", name="add_wiki_root_category")
     * @Route("/wiki/{id}-{slug}/categories/add", name="add_wiki_category")
     */
    public function addCategory(Request $request, WikiCategory $parentCategory = null) {
        $this->denyAccessUnlessGranted(WikiVoter::ADD, $parentCategory);

        $category = (new WikiCategory())
            ->setParent($parentCategory);

        $form = $this->createForm(WikiCategoryType::class, $category, [ ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $this->makeSlug($category);

            $em = $this->getDoctrine()->getManager();
            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'wiki.categories.add.success');

            if($parentCategory === null) {
                return $this->redirectToRoute('wiki');
            }

            return $this->redirectToRoute('wiki_category', [
                'id' => $parentCategory->getId(),
                'slug' => $parentCategory->getSlug()
            ]);
        }

        return $this->render('wiki/categories/add.html.twig', [
            'category' => $category,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/wiki/{id}-{slug}/edit", name="edit_wiki_category")
     */
    public function editCategory(Request $request, WikiCategory $category) {
        $this->denyAccessUnlessGranted(WikiVoter::EDIT, $category);

        $form = $this->createForm(WikiCategoryType::class, $category, [ ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $this->makeSlug($category);

            $em = $this->getDoctrine()->getManager();
            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'wiki.categories.edit.success');

            return $this->redirectToRoute('wiki_category', [
                'id' => $category->getId(),
                'slug' => $category->getSlug()
            ]);
        }

        return $this->render('wiki/categories/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/wiki/{id}-{slug}/remove", name="remove_wiki_category")
     */
    public function removeCategory(Request $request, WikiCategory $category) {
        $this->denyAccessUnlessGranted(WikiVoter::DELETE, $category);

        if($category->getArticles()->count() > 0) {
            $this->addFlash('error', 'wiki.categores.remove.error.articles');

            return $this->redirectToRoute('wiki_category', [
                'id' => $category->getId(),
                'slug' => $category->getSlug()
            ]);
        }

        if($category->getCategories()->count() > 0) {
            $this->addFlash('error', 'wiki.categores.remove.error.categories');

            return $this->redirectToRoute('wiki_category', [
                'id' => $category->getId(),
                'slug' => $category->getSlug()
            ]);
        }

        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(ConfirmType::class, null, [
            'message' => 'wiki.categories.remove.confirm'
        ]);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $em->remove($category);
            $em->flush();

            $this->addFlash('success', 'wiki.categories.remove.success');

            return $this->redirectToRoute('wiki_category', [
                'id' => $category->getId(),
                'slug' => $category->getSlug()
            ]);
        }

        return $this->render('wiki/articles/remove.html.twig', [
            'form' => $form->createView(),
            'category' => $category
        ]);
    }

    private function makeSlug($subject) {
        if($subject instanceof WikiArticle) {
            $subject->setSlug($this->slugger->slugify($subject->getName()));
        } else if($subject instanceof WikiCategory) {
            $subject->setSlug($this->slugger->slugify($subject->getName()));
        } else {
            throw new \InvalidArgumentException(sprintf('$subject must be either of type "%s" or "%s" ("%s" given)"', WikiArticle::class, WikiCategory::class, get_class($subject)));
        }

        return $subject;
    }
}