<template>
  <div class="legal-page">
    <button class="back-btn" @click="$router.back()">&#8592; Back</button>
    <h1 class="legal-title">Terms of Use</h1>
    <div class="legal-content">
      <p>Last updated: March 2026</p>

      <h2>1. Acceptance of Terms</h2>
      <p>By using Trusted Advisors ("the App"), you agree to these Terms of Use. If you do not agree, do not use the App.</p>

      <h2>2. Description of Service</h2>
      <p>Trusted Advisors is a digital board game that allows users to play cooperative and competitive strategy games. The App offers both free and premium features.</p>

      <h2>3. Premium Subscription</h2>
      <p>Trusted Advisors offers an auto-renewable subscription ("Premium") that provides access to additional features including detailed statistics, custom game creation, private lobbies, tournament mode, and exclusive cosmetics.</p>
      <ul>
        <li>Payment will be charged to your Apple ID account at confirmation of purchase.</li>
        <li>Subscription automatically renews unless auto-renew is turned off at least 24 hours before the end of the current period.</li>
        <li>Your account will be charged for renewal within 24 hours prior to the end of the current period.</li>
        <li>You can manage and cancel your subscriptions by going to your account settings on the App Store after purchase.</li>
        <li>Any unused portion of a free trial period, if offered, will be forfeited when you purchase a subscription.</li>
      </ul>

      <h2>4. User Accounts</h2>
      <p>You are responsible for maintaining the confidentiality of your account and for all activities under your account.</p>

      <h2>5. User Conduct</h2>
      <p>You agree not to use the App to harass, abuse, or harm other users, or to engage in any activity that violates applicable laws.</p>

      <h2>6. Intellectual Property</h2>
      <p>All content, artwork, characters, and game mechanics in Trusted Advisors are the property of the developer. You may not copy, modify, or distribute any part of the App without permission.</p>

      <h2>7. Disclaimer</h2>
      <p>The App is provided "as is" without warranties of any kind. We do not guarantee uninterrupted or error-free operation.</p>

      <h2>8. Limitation of Liability</h2>
      <p>To the maximum extent permitted by law, we shall not be liable for any indirect, incidental, or consequential damages arising from your use of the App.</p>

      <h2>9. Changes to Terms</h2>
      <p>We reserve the right to modify these terms at any time. Continued use of the App after changes constitutes acceptance of the updated terms.</p>

      <h2>10. Contact</h2>
      <p>For questions about these Terms, please contact us through the App's support channels.</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TermsPage',
};
</script>

<style scoped>
.legal-page {
  max-width: 600px;
  margin: 0 auto;
  padding: 0 16px 100px;
}

.back-btn {
  background: none;
  border: 1px solid rgba(138, 106, 46, 0.4);
  color: var(--text-secondary);
  font-size: 0.85rem;
  padding: 6px 14px;
  cursor: pointer;
  margin-bottom: 8px;
  border-radius: 6px;
  letter-spacing: 0;
}

.back-btn:hover {
  color: var(--text-bright);
  border-color: var(--border-gold);
}

.legal-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 16px;
}

.legal-content h2 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
  margin: 20px 0 8px;
}

.legal-content p,
.legal-content li {
  color: var(--text-secondary);
  font-size: 0.9rem;
  line-height: 1.6;
  margin-bottom: 8px;
}

.legal-content ul {
  padding-left: 20px;
  margin-bottom: 12px;
}
</style>
