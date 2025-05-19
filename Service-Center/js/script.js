// Confirmation for delete actions
function confirmDelete(event) {
    if (!confirm('Ви впевнені, що хочете видалити цей запис?')) {
        event.preventDefault();
        return false;
    }
    return true;
}

// Add event listeners when the DOM content is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add confirmation to all delete buttons
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', confirmDelete);
    });

    // Fade out alert messages after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 1s';

                setTimeout(function() {
                    alert.style.display = 'none';
                }, 1000);
            });
        }, 5000);
    }
});