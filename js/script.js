document.addEventListener('DOMContentLoaded', function() {
    
    if (typeof Swiper !== 'undefined') {
        const reviewsSwiper = new Swiper('.reviews-swiper', {
            slidesPerView: 1,
            spaceBetween: 30,
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                640: {
                    slidesPerView: 1,
                },
                768: {
                    slidesPerView: 2,
                },
                1024: {
                    slidesPerView: 3,
                },
            },
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
        });
    }
    
    const stepCards = document.querySelectorAll('.step-card');
    
    stepCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        });
    });
    
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== "#") {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.category-card, .advantage-card, .product-card, .step-card, .review-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
    
    // Mobile menu is handled in includes/header.php + CSS.
    
});

const advantageObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
            setTimeout(() => {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0) scale(1)';
            }, index * 100);
            advantageObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

document.querySelectorAll('.advantage-card').forEach((card, i) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px) scale(0.95)';
    card.style.transition = `all 0.5s ease ${i * 0.1}s`;
    advantageObserver.observe(card);
});

const advCards = document.querySelectorAll('.advantage-card');
advCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.animation = 'pulse 0.5s ease';
        setTimeout(() => {
            this.style.animation = '';
        }, 500);
    });
});

const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.02); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(style);


document.querySelectorAll('.step-card__icon img').forEach(img => {
    img.addEventListener('error', function() {
        const fallback = document.createElement('span');
        fallback.className = 'emoji-icon';
        
        const stepIndex = Array.from(this.closest('.step-card').parentNode.children).indexOf(this.closest('.step-card'));
        const emojis = ['📝', '💬', '🚚', '🛠️'];
        fallback.textContent = emojis[stepIndex] || '📌';
        
        this.parentNode.appendChild(fallback);
        this.style.display = 'none';
    });
});


const modal = document.getElementById('notification-modal');
const modalClose = document.querySelector('.modal-close');
const modalBtn = document.querySelector('.modal-btn');

function closeModal() {
    modal.classList.remove('active');
}

if (modalClose) modalClose.addEventListener('click', closeModal);
if (modalBtn) modalBtn.addEventListener('click', closeModal);
modal?.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

if (typeof Swiper !== 'undefined') {
    new Swiper('.reviews-swiper', {
        slidesPerView: 1,
        spaceBetween: 30,
        pagination: { el: '.swiper-pagination', clickable: true },
        breakpoints: { 640: { slidesPerView: 2 }, 1024: { slidesPerView: 3 } }
    });
}
