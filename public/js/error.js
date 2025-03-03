document.addEventListener('DOMContentLoaded', function() {
    var elm = document.getElementById('error-code');
    var target = parseInt(elm.dataset.target || '0', 10);
    var current = 0;
    var duration = 1500;
    var stepTime = 16;
    var increment = target / (duration / stepTime);
    var slowThreshold = 30;
    var slowDelay = 50;

    function updateCounter() {
        current += increment;
        if (current < target) {
            elm.innerText = Math.floor(current);
            if (target - current < slowThreshold) {
                setTimeout(function() {
                    requestAnimationFrame(updateCounter);
                }, slowDelay);
            } else {
                requestAnimationFrame(updateCounter);
            }
        } else {
            elm.innerText = target;
        }
    }
    requestAnimationFrame(updateCounter);
});
