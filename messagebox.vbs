' Message box Script
Set objArgs = WScript.Arguments 
For I = 0 to objArgs.Count - 1 
    msgbox objArgs(I)                   
Next 
