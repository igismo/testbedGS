<?php
function SPITNEWUSERFORM($formfields, $show_username, $show_password) {
  global $TBDB_UIDLEN;

  #
  # Full Name
  #
  echo "<div>
          <fieldset>
          <legend>New User Information</legend>
        <div class=field>
          <label for=\"formfields[usr_name]\">Your Full Name (include title):</label> 
          <input type=text 
            required 
            aria-required=true 
            name=\"formfields[usr_name]\"
            id=\"formfields[usr_name]\"
            value=\"" . $formfields["usr_name"] . "\"
            >
        </div>\n";

  #
  # Email
  #
  echo "<div class=field>
            <label for=\"formfields[usr_email]\">Contact Email Address: <span class=help-text>Enter the e-mail address most appropriate for
            communication with the DeterLab team.</span></label>  
            <input type=email
              required 
              aria-required=true 
              name=\"formfields[usr_email]\"
              id=\"formfields[usr_email]\"
              value=\"" . $formfields["usr_email"] . "\"
              >
        </div>\n";

  #
  # Phone Number
  #
  echo "<div class=field>
            <label for=\"formfields[usr_phone]\">Contact Phone Number: <span class=help-text>Enter the phone number the DeterLab team may use to contact you.</span></label>  
            <input type=tel
              required 
              aria-required=true 
              name=\"formfields[usr_phone]\"
              id=\"formfields[usr_phone]\"
              value=\"" . $formfields["usr_phone"] . "\"
              >
        </div>\n";

  #
  # Title/Position
  # 
  echo "<div class=field>
            <label for=\"formfields[usr_title]\">Position, Title, or Job Description: <span class=help-text>Your position at your employer, e.g. Research Director.</span> </label> 
            <input type=text
              required 
              aria-required=true 
              name=\"formfields[usr_title]\"
              id=\"formfields[usr_title]\"
              value=\"" . $formfields["usr_title"] . "\"
              >
        </div>\n";

  #
  # Full Affiliation
  # 
  
  echo "<div class=field>
          <label for=\"formfields[usr_affil]\">Affiliated Institution or Employer: <span class=help-text>Enter the full name of an academic institution, corporation, government organization, or NGO that you are employed by or affiliated with.</span></label>  
          <input type=text
            required 
            aria-required=true 
            name=\"formfields[usr_affil]\"
            id=\"formfields[usr_affil]\"
            value=\"" . $formfields["usr_affil"] . "\"
            >
         </div>\n";
  #
  # Abbreviated Afiliation
  #          
      
  echo "<div class=\"field affil-abbrev\">
          <label for=\"formfields[usr_affil_abbrev]\">Abbreviation of your Institution (ie, CalTech or NIST):</label> 
          <input type=text
            required 
            aria-required=true 
            name=\"formfields[usr_affil_abbrev]\"
            id=\"formfields[usr_affil_abbrev]\"
            value=\"" . $formfields["usr_affil_abbrev"] . "\"
            >
        </div>\n";

  #
  # Affiliation URL
  #
  
  echo "<div class=field>
          <label for=\"formfields[usr_URL]\">Institution's Website:</label>
          <input type=url
            required 
            aria-required=true
            name=\"formfields[usr_URL]\"
            id=\"formfields[usr_URL]\"
            value=\"" . $formfields["usr_URL"] . "\"
            >
            </div>
          </fieldset>
        </div>\n";
        
  #
  # Postal Address section
  #

  echo "<div>
          <fieldset class=postal>
            <legend>Institution's Postal Address:</legend>
            <span class=help-text>Use your postal address at your afflilated institution.</span>
            <div class=field>
              <label for=\"formfields[usr_addr]\">Address 1:</label> 
              <input type=text
                required 
                aria-required=true
                name=\"formfields[usr_addr]\"
                id=\"formfields[usr_addr]\"
                value=\"" . $formfields["usr_addr"] . "\"
                >
            </div>
            <div class=field>
              <label for=\"formfields[usr_addr2]\">Address 2:</label> 
              <input type=text
                name=\"formfields[usr_addr2]\"
                id=\"formfields[usr_addr2]\"
                value=\"" . $formfields["usr_addr2"] . "\"
                >
            </div>
            <div class=\"field city\">
              <label for=\"formfields[usr_city]\">City:</label> 
              <input type=text
                required 
                aria-required=true
                name=\"formfields[usr_city]\"
                id=\"formfields[usr_city]\"
                value=\"" . $formfields["usr_city"] . "\"
                >
            </div>
            <div class=\"field state\">
              <label for=\"formfields[usr_state]\">State/Province: <span class=help-text>US/Canada only</span></label> 
              <input type=text
                required 
                aria-required=true
                name=\"formfields[usr_state]\"
                id=\"formfields[usr_state]\"
                value=\"" . $formfields["usr_state"] . "\"
                >
            </div>
            <div class=\"field zip\">
              <label for=\"formfields[usr_zip]\">ZIP/Postal Code:</label> 
              <input type=text
                required 
                aria-required=true
                name=\"formfields[usr_zip]\"
                id=\"formfields[usr_zip]\"
                value=\"" . $formfields["usr_zip"] . "\"
                >
            </div>
            <div class=\"field country\">
              <label for=\"formfields[usr_country]\">Country:</label> 
              <input type=text
                required 
                aria-required=true
                name=\"formfields[usr_country]\"
                id=\"formfields[usr_country]\"
                value=\"" . $formfields["usr_country"] . "\"
                >
            </div>
          </fieldset>
        </div>";

  #
  # Username:
  #  
  echo "<div>
          <fieldset>
            <legend>Your DETERLab Login Credentials:</legend>";
  
  if ($show_username) {
      echo "<div class=field>
              <label for=\"formfields[uid]\">Username (6-to-$TBDB_UIDLEN numbers and letters only):</label>
                <input type=text
                  required
                  aria-required=true
                  name=\"formfields[uid]\"
                  id=\"formfields[uid]\"
                  value=\"" . $formfields["uid"] . "\"
                  maxlength=$TBDB_UIDLEN>
            </div>\n";
  }

  #
  # Password. Note that we do not resend the password. User
  # must retype on error.
  #
  if ($show_password) {
    echo "<div class=field>
            <label for=\"formfields[password1]\">Password:</label> 
            <input type=password
              required
              aria-required=true
              name=\"formfields[password1]\"
              id=\"formfields[password1]\"
              >
        </div>\n";

  echo "<div class=field>
          <label for=\"formfields[password2]\">Re-type Password:</label> 
          <input type=password
            required
            aria-required=true
            name=\"formfields[password2]\"
            id=\"formfields[password2]\"
            >
       </div>\n";
  }
  
  echo "</fieldset>
        </div>";

}

?>
