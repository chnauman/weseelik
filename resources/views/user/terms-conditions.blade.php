@extends('layouts.user')

@section('title', 'Terms & Conditions - Wiseelik')

@section('styles')
<style>
    .terms-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    
    .terms-header {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .terms-header h1 {
        font-size: 36px;
        margin-bottom: 15px;
        color: #ffffff;
    }
    
    .terms-header p {
        color: #a0a0b9;
    }
    
    .terms-section {
        margin-bottom: 30px;
    }
    
    .terms-section h2 {
        font-size: 24px;
        margin-bottom: 15px;
        color: #ffffff;
    }
    
    .terms-section p, .terms-section ul {
        color: #a0a0b9;
        line-height: 1.6;
        margin-bottom: 15px;
    }
    
    .terms-section ul {
        padding-left: 20px;
    }
    
    .terms-section ul li {
        margin-bottom: 10px;
    }
    
    .last-updated {
        font-style: italic;
        color: #a0a0b9;
        margin-top: 40px;
        text-align: center;
    }
</style>
@endsection

@section('content')
<div class="terms-container">
    <div class="terms-header">
        <h1>Terms & Conditions</h1>
        <p>Please read these terms and conditions carefully before using Wiseelik services.</p>
    </div>
    
    <div class="terms-section">
        <h2>1. Acceptance of Terms</h2>
        <p>By accessing or using Wiseelik services, you agree to be bound by these Terms and Conditions and all applicable laws and regulations. If you do not agree with any of these terms, you are prohibited from using or accessing our services.</p>
    </div>
    
    <div class="terms-section">
        <h2>2. Use License</h2>
        <p>Permission is granted to temporarily download one copy of the materials (information or software) on Wiseelik's website or application for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
        <ul>
            <li>Modify or copy the materials;</li>
            <li>Use the materials for any commercial purpose, or for any public display;</li>
            <li>Attempt to decompile or reverse engineer any software contained in Wiseelik;</li>
            <li>Remove any copyright or other proprietary notations from the materials; or</li>
            <li>Transfer the materials to another person or "mirror" the materials on any other server.</li>
        </ul>
        <p>This license shall automatically terminate if you violate any of these restrictions and may be terminated by Wiseelik at any time.</p>
    </div>
    
    <div class="terms-section">
        <h2>3. User Accounts</h2>
        <p>When you create an account with us, you must provide accurate, complete, and current information at all times. Failure to do so constitutes a breach of the Terms, which may result in immediate termination of your account.</p>
        <p>You are responsible for safeguarding the password that you use to access the service and for any activities or actions under your password. You agree not to disclose your password to any third party.</p>
    </div>
    
    <div class="terms-section">
        <h2>4. User Content</h2>
        <p>Our service allows you to post, link, store, share and otherwise make available certain information, text, graphics, videos, or other material. You are responsible for the content you post and its legality, reliability, and appropriateness.</p>
        <p>By posting content, you grant Wiseelik a non-exclusive, transferable, sub-licensable, royalty-free, worldwide license to use, copy, modify, create derivative works based on, distribute, and display your content in any media formats.</p>
    </div>
    
    <div class="terms-section">
        <h2>5. Limitation of Liability</h2>
        <p>In no event shall Wiseelik or its suppliers be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use Wiseelik's services, even if Wiseelik has been notified of the possibility of such damage.</p>
    </div>
    
    <div class="terms-section">
        <h2>6. Governing Law</h2>
        <p>These Terms shall be governed and construed in accordance with the laws applicable in your jurisdiction, without regard to its conflict of law provisions.</p>
        <p>Our failure to enforce any right or provision of these Terms will not be considered a waiver of those rights.</p>
    </div>
    
    <div class="terms-section">
        <h2>7. Changes to Terms</h2>
        <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. By continuing to access or use our service after those revisions become effective, you agree to be bound by the revised terms.</p>
    </div>
    
    <div class="terms-section">
        <h2>8. Contact Us</h2>
        <p>If you have any questions about these Terms, please contact us at:</p>
        <ul>
            <li>Email: legal@wiseelik.com</li>
            <li>Website: www.wiseelik.com/contact</li>
        </ul>
    </div>
    
    <p class="last-updated">Last Updated: November 15, 2023</p>
</div>
@endsection
