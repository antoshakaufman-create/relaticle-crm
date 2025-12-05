"""
Prompt Optimizer - Интеграция с Gemini 2.5 Flash-Lite.
Используется для оптимизации промптов и анализа запросов.
⚠️ Free Tier может использовать контент для улучшения продуктов Google.
"""
import json
from typing import Dict, Any, Optional, List

import google.generativeai as genai

from src.config import GEMINI_API_KEY, GEMINI_MODEL, GEMINI_USE_FREE_TIER, AI_REQUEST_TIMEOUT
from src.utils.logger import logger


# ⚠️ КРИТИЧЕСКОЕ ПРЕДУПРЕЖДЕНИЕ О КОНФИДЕНЦИАЛЬНОСТИ
CONFIDENTIALITY_WARNING = """
⚠️ ВНИМАНИЕ: Использование Free Tier Gemini может позволить Google 
использовать контент для улучшения своих продуктов. Это нарушает 
корпоративные требования к конфиденциальности. Для production 
использования рекомендуется платный план Gemini API.
"""


class PromptOptimizer:
    """
    Оптимизатор промптов на базе Gemini 2.5 Flash-Lite.
    Преобразует неструктурированный ввод в структурированные промпты для AI-моделей.
    """
    
    def __init__(self):
        genai.configure(api_key=GEMINI_API_KEY)
        self.model = genai.GenerativeModel(GEMINI_MODEL)
        
        if GEMINI_USE_FREE_TIER:
            logger.warning(CONFIDENTIALITY_WARNING)
    
    async def optimize_prompt(
        self,
        user_input: str,
        target_model: str = "nbp"
    ) -> Dict[str, Any]:
        """
        Оптимизирует промпт пользователя для целевой модели.
        
        Args:
            user_input: Исходный промпт пользователя
            target_model: Целевая модель (nbp, seedream)
            
        Returns:
            Dict с оптимизированным промптом и метаданными
        """
        try:
            # Инструкции для разных моделей
            model_instructions = ""
            if target_model == "zimage":
                model_instructions = """
Целевая модель: Z-Image Turbo — диффузионная модель, оптимизированная для фотореализма.
Рекомендации для оптимизации:
- Добавь детальные описания освещения, атмосферы, материалов
- Укажи стиль камеры/объектива если уместно (например: "shot on Canon 5D", "35mm film grain")
- Используй художественные термины: bokeh, cinematic lighting, golden hour, etc.
- Для текста на изображении: укажи точный текст в кавычках
- Избегай абстрактных концепций, фокусируйся на визуальных деталях
"""
            elif target_model == "nbp":
                model_instructions = """
Целевая модель: Gemini 3 Pro Image — мультимодальная модель с поддержкой сложной логики.
Рекомендации для оптимизации:
- Можно использовать логические и математические концепции
- Поддерживает сложные композиции и инфографику
- Хорошо работает с точным текстом и диаграммами
- Высокое разрешение до 4K
"""
            else:
                model_instructions = f"Целевая модель: {target_model}"

            optimization_prompt = f"""
Проанализируй и оптимизируй следующий промпт пользователя для генерации изображения.

{model_instructions}

Исходный промпт: {user_input}

Верни JSON с полями:
- "optimized_prompt": оптимизированный промпт для максимального качества (на английском языке для лучших результатов)
- "style": стиль изображения (если указан)
- "resolution_preference": предпочтительное разрешение (1K, 2K, 4K)
- "requires_logic": требует ли промпт логических вычислений (true/false)
- "requires_math": требует ли промпт математических вычислений (true/false)
- "is_artistic": является ли запрос художественным (true/false)
- "complexity": сложность запроса (low, medium, high)
"""
            
            response = await self._call_gemini(optimization_prompt)
            
            # Парсим JSON ответ
            try:
                # Извлекаем JSON из ответа (может быть обернут в markdown)
                json_text = response.text
                if "```json" in json_text:
                    json_text = json_text.split("```json")[1].split("```")[0].strip()
                elif "```" in json_text:
                    json_text = json_text.split("```")[1].split("```")[0].strip()
                
                result = json.loads(json_text)
                result["original_prompt"] = user_input
                
                logger.info(f"Prompt optimized: {user_input[:50]}... -> {result.get('optimized_prompt', '')[:50]}...")
                return result
            except json.JSONDecodeError:
                logger.warning("Failed to parse JSON from Gemini response, using fallback")
                return {
                    "optimized_prompt": user_input,
                    "original_prompt": user_input,
                    "style": None,
                    "resolution_preference": "2K",
                    "requires_logic": False,
                    "requires_math": False,
                    "is_artistic": True,
                    "complexity": "medium"
                }
        except Exception as e:
            logger.error(f"Prompt optimization failed: {e}")
            # Fallback на исходный промпт
            return {
                "optimized_prompt": user_input,
                "original_prompt": user_input,
                "style": None,
                "resolution_preference": "2K",
                "requires_logic": False,
                "requires_math": False,
                "is_artistic": True,
                "complexity": "medium"
            }
    
    async def analyze_request(
        self,
        user_input: str
    ) -> Dict[str, Any]:
        """
        Анализирует запрос пользователя для определения оптимальной модели.
        
        Args:
            user_input: Запрос пользователя
            
        Returns:
            Dict с анализом запроса для роутера
        """
        try:
            analysis_prompt = f"""
Проанализируй следующий запрос пользователя и определи:
1. Требует ли запрос сложной логики или математики
2. Является ли запрос художественным/общим
3. Нужно ли финальное 4K разрешение
4. Это превью или финальный запрос

Запрос: {user_input}

Верни JSON с полями:
- "requires_logic": требует ли сложной логики (true/false)
- "requires_math": требует ли математики (true/false)
- "is_general_artistic": является ли общим художественным запросом (true/false)
- "is_preview": это превью запрос (true/false)
- "requires_4k_final": требует ли финального 4K (true/false)
- "recommended_model": рекомендуемая модель (seedream/nbp)
"""
            
            response = await self._call_gemini(analysis_prompt)
            
            # Парсим JSON
            try:
                json_text = response.text
                if "```json" in json_text:
                    json_text = json_text.split("```json")[1].split("```")[0].strip()
                elif "```" in json_text:
                    json_text = json_text.split("```")[1].split("```")[0].strip()
                
                result = json.loads(json_text)
                logger.info(f"Request analyzed: {result.get('recommended_model', 'unknown')}")
                return result
            except json.JSONDecodeError:
                logger.warning("Failed to parse analysis JSON, using defaults")
                return {
                    "requires_logic": False,
                    "requires_math": False,
                    "is_general_artistic": True,
                    "is_preview": True,
                    "requires_4k_final": False,
                    "recommended_model": "seedream"
                }
        except Exception as e:
            logger.error(f"Request analysis failed: {e}")
            return {
                "requires_logic": False,
                "requires_math": False,
                "is_general_artistic": True,
                "is_preview": True,
                "requires_4k_final": False,
                "recommended_model": "seedream"
            }
    
    async def summarize_context(
        self,
        messages: List[Dict[str, str]],
        max_tokens: int = 500
    ) -> str:
        """
        Суммаризирует контекст диалога для экономии токенов.
        
        Args:
            messages: Список сообщений диалога
            max_tokens: Максимальная длина суммаризации
            
        Returns:
            Суммаризированный контекст
        """
        try:
            context_text = "\n".join([
                f"{msg.get('role', 'user')}: {msg.get('content', '')}"
                for msg in messages[-10:]  # Последние 10 сообщений
            ])
            
            summary_prompt = f"""
Суммаризируй следующий контекст диалога, сохраняя ключевую информацию.
Максимальная длина: {max_tokens} токенов.

Контекст:
{context_text}
"""
            
            response = await self._call_gemini(summary_prompt)
            return response.text
        except Exception as e:
            logger.error(f"Context summarization failed: {e}")
            return ""
    
    async def _call_gemini(self, prompt: str):
        """Внутренний метод для вызова Gemini API."""
        import asyncio
        
        # Синхронный вызов в async обертке
        loop = asyncio.get_event_loop()
        response = await loop.run_in_executor(
            None,
            lambda: self.model.generate_content(prompt)
        )
        return response


# Глобальный экземпляр оптимизатора
prompt_optimizer = PromptOptimizer()

