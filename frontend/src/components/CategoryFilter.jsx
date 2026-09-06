import React from 'react';
import { useApp } from '../context/AppContext';

export default function CategoryFilter({ categories, selectedCategoryId, onSelectCategory }) {
  const { t } = useApp();

  return (
    <div className="flex items-center gap-2 overflow-x-auto pb-3 scrollbar-none">
      <button
        onClick={() => onSelectCategory(null)}
        className={`px-5 py-2 rounded-full text-sm font-semibold transition-all whitespace-nowrap shadow-2xs ${
          selectedCategoryId === null
            ? 'bg-emerald-700 text-white shadow-md shadow-emerald-700/20'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
        }`}
      >
        {t.all_categories}
      </button>

      {categories.map((cat) => {
        const catId = cat.base_category_id || cat.id;
        const isSelected = selectedCategoryId === catId;
        return (
          <button
            key={cat.id || catId}
            onClick={() => onSelectCategory(catId)}
            className={`px-5 py-2 rounded-full text-sm font-semibold transition-all whitespace-nowrap shadow-2xs ${
              isSelected
                ? 'bg-emerald-700 text-white shadow-md shadow-emerald-700/20'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            {cat.name}
            {cat.product_count !== undefined && (
              <span className={`ml-1.5 text-xs opacity-75 ${isSelected ? 'text-white' : 'text-gray-500'}`}>
                ({cat.product_count})
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}
