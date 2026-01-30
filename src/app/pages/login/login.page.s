// Variables de colores
$bg-dark: #0a1f1a;
$bg-darker: #061512;
$green-primary: #00ff88;
$green-dark: #00cc6e;
$text-white: #ffffff;
$text-gray: #a0b0a8;
$border-color: #1a3d32;

.login-content {
  --background: linear-gradient(180deg, #{$bg-darker} 0%, #{$bg-dark} 50%, #{$bg-darker} 100%);
}

.login-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 20px;
}

// Logo
.logo-container {
  margin-bottom: 40px;
  
  .logo-box {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, $green-primary 0%, $green-dark 100%);
    border-radius: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 32px rgba(0, 255, 136, 0.3);
    
    ion-icon {
      font-size: 64px;
      color: #1a3d32;
    }
  }
}

// Welcome Text
.welcome-text {
  text-align: center;
  margin-bottom: 50px;
  
  h1 {
    font-size: 36px;
    font-weight: 700;
    color: $text-white;
    margin: 0 0 12px 0;
    line-height: 1.2;
    
    .highlight {
      color: $green-primary;
    }
  }
  
  .subtitle {
    font-size: 16px;
    color: $text-gray;
    margin: 0;
    font-weight: 400;
  }
}

// Form
.login-form {
  width: 100%;
  max-width: 600px;
}

// Input Groups
.input-group {
  margin-bottom: 24px;
  
  .input-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.2px;
    color: $text-white;
    margin-bottom: 12px;
  }
  
  .input-wrapper {
    position: relative;
    
    .custom-input {
      width: 100%;
      padding: 20px 50px 20px 20px;
      background: rgba(26, 61, 50, 0.3);
      border: 2px solid $border-color;
      border-radius: 16px;
      color: $text-white;
      font-size: 16px;
      transition: all 0.3s ease;
      
      &::placeholder {
        color: $text-gray;
      }
      
      &:focus {
        outline: none;
        border-color: $green-primary;
        background: rgba(26, 61, 50, 0.5);
        box-shadow: 0 0 0 3px rgba(0, 255, 136, 0.1);
      }
    }
    
    .input-icon {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 24px;
      color: $text-gray;
      pointer-events: none;
    }
  }
}

// Forgot Password
.forgot-password {
  text-align: right;
  margin-bottom: 32px;
  
  .forgot-link {
    color: $green-primary;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: color 0.3s ease;
    
    &:hover {
      color: $green-dark;
    }
  }
}

// Login Button
.login-button {
  --background: linear-gradient(135deg, #{$green-primary} 0%, #{$green-dark} 100%);
  --background-activated: #{$green-dark};
  --background-hover: #{$green-dark};
  --border-radius: 16px;
  --box-shadow: 0 8px 24px rgba(0, 255, 136, 0.3);
  
  height: 60px;
  font-size: 16px;
  font-weight: 700;
  letter-spacing: 1px;
  color: #1a3d32;
  margin-bottom: 32px;
  text-transform: uppercase;
}

// Divider
.divider {
  display: flex;
  align-items: center;
  margin: 32px 0;
  
  &::before,
  &::after {
    content: '';
    flex: 1;
    height: 1px;
    background: $border-color;
  }
  
  .divider-text {
    padding: 0 20px;
    font-size: 12px;
    color: $text-gray;
    font-weight: 600;
    letter-spacing: 0.5px;
  }
}

// Social Buttons
.social-buttons {
  display: flex;
  gap: 16px;
  margin-bottom: 32px;
  
  .social-button {
    flex: 1;
    height: 60px;
    background: rgba(26, 61, 50, 0.3);
    border: 2px solid $border-color;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    cursor: pointer;
    
    ion-icon {
      font-size: 28px;
      color: $text-white;
    }
    
    &:hover {
      border-color: $green-primary;
      background: rgba(26, 61, 50, 0.5);
      transform: translateY(-2px);
    }
    
    &:active {
      transform: translateY(0);
    }
  }
}

// Sign Up Text
.signup-text {
  text-align: center;
  font-size: 14px;
  color: $text-gray;
  
  .signup-link {
    color: $green-primary;
    text-decoration: none;
    font-weight: 600;
    margin-left: 4px;
    transition: color 0.3s ease;
    
    &:hover {
      color: $green-dark;
    }
  }
}

// Responsive
@media (max-width: 768px) {
  .welcome-text h1 {
    font-size: 28px;
  }
  
  .logo-container .logo-box {
    width: 100px;
    height: 100px;
    
    ion-icon {
      font-size: 52px;
    }
  }
}