"""
Intelligent Agent - Интеллектуальный роутер запросов.
Определяет оптимальную модель на основе типа задачи, бюджета и требований к качеству.
"""
from typing import Dict, Any, Optional

from src.prompt_optimizer import prompt_optimizer
from src.config import RESOLUTION_2K, RESOLUTION_4K
from src.utils.logger import logger


class AIAgent:
    """
    Интеллектуальный роутер для выбора оптимальной AI-модели.
    Принимает решения на основе анализа запроса, бюджета и требований к качеству.
    """
    
    def __init__(self):
        self.optimizer = prompt_optimizer
    
    async def route_request(
        self,
        user_input: str,
        context: Optional[Dict[str, Any]] = None
    ) -> Dict[str, Any]:
        """
        Определяет оптимальную модель для запроса.
        
        Args:
            user_input: Запрос пользователя
            context: Дополнительный контекст (опционально)
            
        Returns:
            Dict с информацией о выбранной модели и причиной выбора
        """
        try:
            # Анализ запроса через Gemini 2.5 Flash-Lite
            analysis = await self.optimizer.analyze_request(user_input)
            
            # Логика роутинга
            recommended_model = analysis.get("recommended_model", "seedream")
            requires_logic = analysis.get("requires_logic", False)
            requires_math = analysis.get("requires_math", False)
            is_general_artistic = analysis.get("is_general_artistic", True)
            is_preview = analysis.get("is_preview", True)
            requires_4k_final = analysis.get("requires_4k_final", False)
            
            # Приоритет 1: Сложная логика/математика -> NBP
            if requires_logic or requires_math:
                logger.info(f"Routing to NBP: complex logic/math required")
                return {
                    "model": "nbp",
                    "reason": "complex_logic",
                    "resolution": RESOLUTION_4K if requires_4k_final else RESOLUTION_2K,
                    "analysis": analysis
                }
            
            # Приоритет 2: Финальный 4K запрос -> NBP
            if requires_4k_final and not is_preview:
                logger.info(f"Routing to NBP: final 4K required")
                return {
                    "model": "nbp",
                    "reason": "final_4k",
                    "resolution": RESOLUTION_4K,
                    "analysis": analysis
                }
            
            # Приоритет 3: Превью или общий художественный запрос -> NBP (Seedream временно отключен)
            if is_preview or is_general_artistic:
                logger.info(f"Routing to NBP: preview/general artistic (Seedream disabled)")
                return {
                    "model": "nbp",
                    "reason": "preview_nbp_fallback",
                    "resolution": RESOLUTION_2K,
                    "analysis": analysis
                }
            
            # По умолчанию - NBP (Seedream временно отключен)
            logger.info(f"Routing to NBP: default option (Seedream disabled)")
            return {
                "model": "nbp",
                "reason": "default_nbp_fallback",
                "resolution": RESOLUTION_2K,
                "analysis": analysis
            }
        except Exception as e:
            logger.error(f"Routing failed: {e}, using default (Seedream)")
            return {
                "model": "nbp",
                "reason": "error_fallback_nbp",
                "resolution": RESOLUTION_2K,
                "analysis": {}
            }
    
    async def should_use_tiered_preview(
        self,
        user_input: str,
        context: Optional[Dict[str, Any]] = None
    ) -> bool:
        """
        Определяет, нужно ли использовать Tiered Preview стратегию.
        
        Args:
            user_input: Запрос пользователя
            context: Контекст запроса
            
        Returns:
            bool: True если рекомендуется Tiered Preview
        """
        analysis = await self.optimizer.analyze_request(user_input)
        
        # Tiered Preview рекомендуется для:
        # 1. Запросов, которые могут потребовать финального 4K
        # 2. Сложных запросов, где пользователь должен подтвердить превью
        requires_4k = analysis.get("requires_4k_final", False)
        complexity = analysis.get("complexity", "medium")
        
        return requires_4k or complexity in ["high", "medium"]


# Глобальный экземпляр агента
agent = AIAgent()

