<?php
$pageTitle  = 'Contact Us — APEX Learning Hub';
$activePage = 'contact';
include '_partials/head.php';
?>

<main>

<div class="page-band">
    <h1 data-lang-en>Contact Us</h1>
    <h1 data-lang-fil style="display:none;">Makipag-ugnayan sa Amin</h1>
    <p data-lang-en>Have a question, concern, or feedback? Reach out and we'll get back to you as soon as possible.</p>
    <p data-lang-fil style="display:none;">May tanong, alalahanin, o feedback? Makipag-ugnayan at tutugon kami sa lalong madaling panahon.</p>
</div>

<section class="section" id="info">
    <div class="container">
        <div class="contact-layout">

            
            <div class="contact-form-card">
                <h2 data-lang-en>Send Us a Message</h2>
                <h2 data-lang-fil style="display:none;">Mag-message sa Amin</h2>

                <form id="contactForm" novalidate>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label for="firstName" data-lang-en>First Name</label>
                            <label for="firstName" data-lang-fil style="display:none;">Unang Pangalan</label>
                            <input
                                type="text"
                                id="firstName"
                                name="first_name"
                                placeholder="John"
                                required
                            >
                            <span class="form-error-msg" data-lang-en>First name is required.</span>
                            <span class="form-error-msg" data-lang-fil style="display:none;">Kailangan ang unang pangalan.</span>
                        </div>
                        <div class="form-group">
                            <label for="lastName" data-lang-en>Last Name</label>
                            <label for="lastName" data-lang-fil style="display:none;">Apelyido</label>
                            <input
                                type="text"
                                id="lastName"
                                name="last_name"
                                placeholder="Doe"
                                required
                            >
                            <span class="form-error-msg" data-lang-en>Last name is required.</span>
                            <span class="form-error-msg" data-lang-fil style="display:none;">Kailangan ang apelyido.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email" data-lang-en>Email Address</label>
                        <label for="email" data-lang-fil style="display:none;">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="you@example.com"
                            required
                        >
                        <span class="form-error-msg" data-lang-en>Email is required.</span>
                        <span class="form-error-msg" data-lang-fil style="display:none;">Kailangan ang email.</span>
                    </div>

                    <div class="form-group">
                        <label for="subject" data-lang-en>Subject / Concern</label>
                        <label for="subject" data-lang-fil style="display:none;">Paksa / Alalahanin</label>
                        <select id="subject" name="subject" required>
                            <option value="" disabled selected data-lang-en>Select a topic...</option>
                            <option value="enrollment" data-lang-en>Enrollment Inquiry</option>
                            <option value="policy" data-lang-en>Policy Question</option>
                            <option value="technical" data-lang-en>Technical Support</option>
                            <option value="announcement" data-lang-en>Announcement / Bulletin</option>
                            <option value="other" data-lang-en>Other</option>
                        </select>
                        <span class="form-error-msg" data-lang-en>Please select a subject.</span>
                        <span class="form-error-msg" data-lang-fil style="display:none;">Mangyaring pumili ng paksa.</span>
                    </div>

                    <div class="form-group">
                        <label for="message" data-lang-en>Message</label>
                        <label for="message" data-lang-fil style="display:none;">Mensahe</label>
                        <textarea
                            id="message"
                            name="message"
                            placeholder="Describe your question or concern in detail..."
                            required
                        ></textarea>
                        <span class="form-error-msg" data-lang-en>Message cannot be empty.</span>
                        <span class="form-error-msg" data-lang-fil style="display:none;">Hindi maaaring walang mensahe.</span>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        <span data-lang-en>Send Message</span>
                        <span data-lang-fil style="display:none;">Ipadala ang Mensahe</span>
                    </button>

                    <div id="formSuccess" class="form-success">
                        <span data-lang-en>Your message has been sent. We'll get back to you within 1–2 business days.</span>
                        <span data-lang-fil style="display:none;">Ang iyong mensahe ay naipadala na. Magreresponde kami sa loob ng 1–2 araw ng negosyo.</span>
                    </div>
                </form>
            </div>

            
            <div class="contact-sidebar">

                <div class="contact-info-card">
                    <h3 data-lang-en>Office Information</h3>
                    <h3 data-lang-fil style="display:none;">Impormasyon ng Opisina</h3>
                    <div class="contact-detail">
                        <div class="contact-detail-row">
                            <strong data-lang-en>Address</strong>
                            <strong data-lang-fil style="display:none;">Address</strong>
                            <span>123 Academic Avenue, Learning District, Metro Manila, Philippines</span>
                        </div>
                        <div class="contact-detail-row">
                            <strong data-lang-en>Email</strong>
                            <strong data-lang-fil style="display:none;">Email</strong>
                            <span>support@apexlearninghub.edu.ph</span>
                        </div>
                        <div class="contact-detail-row">
                            <strong data-lang-en>Phone</strong>
                            <strong data-lang-fil style="display:none;">Telepono</strong>
                            <span>(02) 8123-4567</span>
                        </div>
                        <div class="contact-detail-row">
                            <strong data-lang-en>Office Hours</strong>
                            <strong data-lang-fil style="display:none;">Oras ng Opisina</strong>
                            <span data-lang-en>Monday – Friday, 8:00 AM – 5:00 PM</span>
                            <span data-lang-fil style="display:none;">Lunes – Biyernes, 8:00 AM – 5:00 PM</span>
                        </div>
                    </div>
                </div>

                <div class="contact-info-card" id="faq">
                    <h3 data-lang-en>Frequently Asked Questions</h3>
                    <h3 data-lang-fil style="display:none;">Mga Madalas Itanong</h3>
                    <div class="contact-detail" style="gap: 14px;">
                        <div class="contact-detail-row">
                            <strong data-lang-en>How do I reset my portal password?</strong>
                            <strong data-lang-fil style="display:none;">Paano ko i-reset ang aking password sa portal?</strong>
                            <span data-lang-en>Click "Forgot Password" on the login page and follow the steps sent to your registered email.</span>
                            <span data-lang-fil style="display:none;">I-click ang "Forgot Password" sa login page at sundin ang mga hakbang na ipinadala sa iyong registered na email.</span>
                        </div>
                        <div class="contact-detail-row">
                            <strong data-lang-en>When does enrollment open?</strong>
                            <strong data-lang-fil style="display:none;">Kailan magbubukas ang pagpapatala?</strong>
                            <span data-lang-en>Enrollment schedules are announced on the Announcements page. Check regularly for updates.</span>
                            <span data-lang-fil style="display:none;">Ang mga iskedyul ng pagpapatala ay inihayag sa Announcements page. Suriin nang regular para sa mga update.</span>
                        </div>
                        <div class="contact-detail-row">
                            <strong data-lang-en>Where can I get my TOR or records?</strong>
                            <strong data-lang-fil style="display:none;">Saan ako makakakuha ng TOR o mga rekord?</strong>
                            <span data-lang-en>Visit the Registrar's Office in person or submit a request through the contact form above.</span>
                            <span data-lang-fil style="display:none;">Bisitahin ang Registrar's Office nang personal o magsumite ng kahilingan sa pamamagitan ng contact form sa itaas.</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

</main>

<?php include '_partials/foot.php'; ?>

