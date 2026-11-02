<?php

declare(strict_types=1);

namespace App\Actions\Category;

use App\DTOs\Category\GetCategoryDTO;
use App\Models\Category;
use App\Services\CategoryService;
use App\Exceptions\NotFoundException;

class GetCategoryAction
{
    public function __construct(
        private CategoryService $categoryService,
    ) {}

    public function execute(GetCategoryDTO $dto): Category
    {
        $category = $this->categoryService->getCategoryById($dto->id);

        if (!$category) {
            throw new NotFoundException(__('error.category_not_found'));
        }

        return $category->loadMissing('mediaLinks.media');
    }
}
