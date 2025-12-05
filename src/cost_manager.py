"""
Cost Manager - Управление затратами с оптимизированной Tiered Preview стратегией.
Seedream ($0.03) для превью -> NBP 4K ($0.24 или $0.12 через KIE) для финала.
"""
from typing import Dict, Optional, List
from datetime import datetime

from src.config import (
    SEEDREAM_PRICE_PER_IMAGE,
    NBP_PRICE_2K,
    NBP_PRICE_4K,
    NBP_PRICE_4K_KIE,
    NBP_USE_KIE_API,
    RESOLUTION_2K
)
from src.seedream_client import seedream_client
from src.nbp_client import nbp_client
from src.agent import agent
from src.prompt_optimizer import prompt_optimizer
from src.utils.logger import logger, log_cost_operation


class CostManager:
    """
    Менеджер затрат с оптимизированной Tiered Preview стратегией.
    Трекинг токенов и стоимости AI-операций.
    """
    
    def __init__(self):
        self.total_cost = 0.0
        self.operations: List[Dict] = []
        self.user_costs: Dict[int, float] = {}  # user_id -> total_cost
    
    async def tiered_preview_workflow(
        self,
        user_request: str,
        user_id: int,
        context: Optional[Dict] = None
    ) -> Dict[str, any]:
        """
        Бюджетная Tiered Preview стратегия:
        Шаг 1: Seedream 4.0 превью ($0.03)
        Шаг 2: После подтверждения - NBP 4K финал ($0.24 или $0.12 через KIE)
        
        Args:
            user_request: Запрос пользователя
            user_id: ID пользователя
            context: Дополнительный контекст
            
        Returns:
            Dict с результатами: preview_image, cost_preview, и инструкциями для финала
        """
        try:
            # Оптимизация промпта
            optimized = await prompt_optimizer.optimize_prompt(user_request, target_model="nbp")
            optimized_prompt = optimized.get("optimized_prompt", user_request)
            
            # Шаг 1: Генерация превью через NBP (Seedream временно отключен)
            logger.info(f"Tiered Preview Step 1: Generating preview via NBP (Seedream disabled)")
            preview_image = await nbp_client.generate_image(
                prompt=optimized_prompt,
                resolution=RESOLUTION_2K,  # 2K для превью
                user_id=user_id
            )
            
            if not preview_image:
                return {
                    "success": False,
                    "error": "Failed to generate preview"
                }
            
            cost_preview = NBP_PRICE_2K  # Используем NBP вместо Seedream
            self._record_operation("preview", "nbp", cost_preview, user_id)
            
            # Подготовка данных для финальной генерации
            final_prompt = await prompt_optimizer.optimize_prompt(
                user_request,
                target_model="nbp"
            )
            
            # Определяем стоимость финала
            final_cost = NBP_PRICE_4K_KIE if NBP_USE_KIE_API else NBP_PRICE_4K
            
            return {
                "success": True,
                "preview_image": preview_image,
                "cost_preview": cost_preview,
                "final_prompt": final_prompt.get("optimized_prompt", user_request),
                "final_cost": final_cost,
                "total_estimated_cost": cost_preview + final_cost,
                "workflow": "tiered_preview"
            }
        except Exception as e:
            logger.error(f"Tiered preview workflow failed: {e}")
            return {
                "success": False,
                "error": str(e)
            }
    
    async def generate_final_4k(
        self,
        prompt: str,
        user_id: int,
        multimodal_inputs: Optional[List] = None
    ) -> Optional[bytes]:
        """
        Генерация финального 4K изображения через NBP.
        Вызывается после подтверждения пользователем превью.
        
        Args:
            prompt: Оптимизированный промпт для финала
            user_id: ID пользователя
            multimodal_inputs: Мультимодальные входы (опционально)
            
        Returns:
            bytes: 4K изображение или None при ошибке
        """
        try:
            logger.info(f"Generating final 4K via NBP for user {user_id}")
            
            final_image = await nbp_client.generate_4k(
                prompt=prompt,
                multimodal_inputs=multimodal_inputs,
                user_id=user_id
            )
            
            if final_image:
                cost = NBP_PRICE_4K_KIE if NBP_USE_KIE_API else NBP_PRICE_4K
                self._record_operation("final", "nbp", cost, user_id)
                logger.info(f"Final 4K generated: ${cost:.4f}")
            
            return final_image
        except Exception as e:
            logger.error(f"Final 4K generation failed: {e}")
            return None
    
    def _record_operation(
        self,
        operation: str,
        model: str,
        cost: float,
        user_id: Optional[int] = None
    ):
        """
        Записывает операцию для трекинга затрат.
        
        Args:
            operation: Тип операции (preview, final, generate)
            model: Использованная модель
            cost: Стоимость операции
            user_id: ID пользователя
        """
        operation_record = {
            "timestamp": datetime.now().isoformat(),
            "operation": operation,
            "model": model,
            "cost": cost,
            "user_id": user_id
        }
        
        self.operations.append(operation_record)
        self.total_cost += cost
        
        if user_id:
            if user_id not in self.user_costs:
                self.user_costs[user_id] = 0.0
            self.user_costs[user_id] += cost
        
        log_cost_operation(operation, model, cost, user_id)
    
    def get_total_cost(self) -> float:
        """Возвращает общую стоимость всех операций."""
        return self.total_cost
    
    def get_user_cost(self, user_id: int) -> float:
        """Возвращает стоимость операций для конкретного пользователя."""
        return self.user_costs.get(user_id, 0.0)
    
    def get_statistics(self) -> Dict:
        """
        Возвращает статистику по затратам.
        
        Returns:
            Dict с общей статистикой
        """
        return {
            "total_cost": self.total_cost,
            "total_operations": len(self.operations),
            "users_count": len(self.user_costs),
            "average_cost_per_operation": (
                self.total_cost / len(self.operations)
                if self.operations else 0.0
            ),
            "operations_by_model": self._count_by_model(),
            "operations_by_type": self._count_by_type()
        }
    
    def _count_by_model(self) -> Dict[str, int]:
        """Подсчет операций по моделям."""
        counts = {}
        for op in self.operations:
            model = op["model"]
            counts[model] = counts.get(model, 0) + 1
        return counts
    
    def _count_by_type(self) -> Dict[str, int]:
        """Подсчет операций по типам."""
        counts = {}
        for op in self.operations:
            op_type = op["operation"]
            counts[op_type] = counts.get(op_type, 0) + 1
        return counts


# Глобальный экземпляр менеджера затрат
cost_manager = CostManager()

