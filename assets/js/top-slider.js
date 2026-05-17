(function() {
    'use strict';

    function initSlider(root) {
        var slides = Array.prototype.slice.call(root.querySelectorAll('.scg-top-slide'));
        var dots = Array.prototype.slice.call(root.querySelectorAll('.scg-top-slider-dot'));
        var prev = root.querySelector('.scg-top-slider-prev');
        var next = root.querySelector('.scg-top-slider-next');
        var index = 0;
        var timer = null;
        var autoplay = root.dataset.autoplay === 'true';
        var interval = parseInt(root.dataset.interval || '5000', 10);
        var fade = parseInt(root.dataset.fade || '900', 10);

        if (slides.length <= 1) {
            return;
        }

        function show(nextIndex) {
            if (nextIndex === index) {
                return;
            }
            var current = index;
            index = (nextIndex + slides.length) % slides.length;

            slides[current].classList.remove('is-active');
            slides[current].classList.add('is-leaving');
            slides[index].classList.add('is-active');
            slides[index].classList.remove('is-leaving');

            dots.forEach(function(dot, i) {
                dot.classList.toggle('is-active', i === index);
            });

            window.setTimeout(function() {
                slides[current].classList.remove('is-leaving');
            }, fade + 80);
        }

        function go(delta) {
            show(index + delta);
        }

        function start() {
            if (!autoplay) return;
            stop();
            timer = window.setInterval(function() {
                go(1);
            }, Math.max(interval, fade + 400));
        }

        function stop() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        if (prev) {
            prev.addEventListener('click', function() {
                go(-1);
                start();
            });
        }
        if (next) {
            next.addEventListener('click', function() {
                go(1);
                start();
            });
        }

        dots.forEach(function(dot) {
            dot.addEventListener('click', function() {
                show(parseInt(dot.dataset.index || '0', 10));
                start();
            });
        });

        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);

        start();
    }

    document.addEventListener('DOMContentLoaded', function() {
        Array.prototype.slice.call(document.querySelectorAll('.scg-top-slider')).forEach(initSlider);
    });
})();
