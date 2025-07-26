@extends('layouts.user')

@section('title', 'Privacy Policy - Wiseelik')

@section('styles')
<style>
    .privacy-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    
    .privacy-header {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .privacy-header h1 {
        font-size: 36px;
        margin-bottom: 15px;
        color: #ffffff;
    }
    
    .privacy-header p {
        color: #a0a0b9;
    }
    
    .privacy-section {
        margin-bottom: 30px;
    }
    
    .privacy-section h2 {
        font-size: 24px;
        margin-bottom: 15px;
        color: #ffffff;
    }
    
    .privacy-section p, .privacy-section ul {
        color: #a0a0b9;
        line-height: 1.6;
        margin-bottom: 15px;
    }
    
    .privacy-section ul {
        padding-left: 20px;
    }
    
    .privacy-section ul li {
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
<div class="privacy-container">
    <div class="privacy-header">
        <h1>Privacy Policy</h1>
        <p>Your privacy is important to us. This Privacy Policy explains how Wiseelik collects, uses, and protects your information.</p>
    </div>
    
    <div class="privacy-section">
        <h2>Information We Collect</h2>
        <p>We may collect the following types of information:</p>
        <ul>
            <li><strong>Personal Information:</strong> Name, email address, phone number, and other details you provide when creating an account or using our services.</li>
            <li><strong>Usage Data:</strong> Information about how you use our application, including interaction patterns, features used, and time spent on various sections.</li>
            <li><strong>Device Information:</strong> Details about the device you use to access our service, such as device type, operating system, and browser information.</li>
        </ul>
    </div>
    
    <div class="privacy-section">
        <h2>How We Use Your Information</h2>
        <p>We use the collected information for various purposes including:</p>
        <ul>
            <li>Providing and maintaining our services</li>
            <li>Notifying you about changes to our services</li>
            <li>Allowing you to participate in interactive features</li>
            <li>Providing customer support</li>
            <li>Gathering analysis to improve our services</li>
            <li>Monitoring service usage</li>
            <li>Detecting, preventing, and addressing technical issues</li>
        </ul>
    </div>
    
    <div class="privacy-section">
        <h2>Data Security</h2>
        <p>The security of your data is important to us, but remember that no method of transmission over the Internet, or method of electronic storage is 100% secure. While we strive to use commercially acceptable means to protect your personal information, we cannot guarantee its absolute security.</p>
    </div>
    
    <div class="privacy-section">
        <h2>Third-Party Services</h2>
        <p>Our service may contain links to other sites that are not operated by us. If you click on a third-party link, you will be directed to that third party's site. We strongly advise you to review the Privacy Policy of every site you visit.</p>
        <p>We have no control over and assume no responsibility for the content, privacy policies, or practices of any third-party sites or services.</p>
    </div>
    
    <div class="privacy-section">
        <h2>Changes to This Privacy Policy</h2>
        <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date.</p>
        <p>You are advised to review this Privacy Policy periodically for any changes. Changes to this Privacy Policy are effective when they are posted on this page.</p>
    </div>
    
    <div class="privacy-section">
        <h2>Contact Us</h2>
        <p>If you have any questions about this Privacy Policy, please contact us at:</p>
        <ul>
            <li>Email: privacy@wiseelik.com</li>
            <li>Website: www.wiseelik.com/contact</li>
        </ul>
    </div>
    
    <p class="last-updated">Last Updated: November 15, 2023</p>
</div>
@endsection
