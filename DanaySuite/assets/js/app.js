document.querySelectorAll(".task-item input[type='checkbox']").forEach((checkbox) => {
    checkbox.addEventListener("click", (event) => {
        event.stopPropagation();
    });
});
