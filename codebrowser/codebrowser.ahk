;
; AutoHotkey Version: 1.x
; Language: English
; Platform: Win9x/NT
; Author: Borja Morales
;
; Script Function:
;	Opens the urls like codebrowser:path_to_file:line in the configured editor.
;
; History:
;	2011 01 31	Added the {MyDocuments} var in ini file.
;	2011 01 31	Support for testing environment.
;	2011 01 14	Add support for Geany.
;	2011 01 03	Opens files directly from production.
;	2010 12 16	Fix char '|' escape in chrome.
;	2010 12 15	Manage information before finishing app.
;	2010 12 10	Added support for -1 line number.
;	2010 12 07	Added configuration of editor via ini file.
;				Added registration of the codebrowser protocol in the windows registry.
;	2011 11 25	Added support for Sublime and phpStorm.
;				Allow different editors for different extensions.
;	2011 12 11	Load different ini sections depending on machine.
;	2012 01 30	Open directly file line by its contents.
;	2012 03 03	Debug mode.
;	2012 06 20	Change 1st run options.
;	2012 07 16	Allow spaces in the parameters.
;	2012 08 20	Load programs.
;	2012 09 20	Allow run other scripts from browser.
;	2012 12 11	Allow parameter when running external programs.
;	2013 04 02	Allow merge from link.
;	2013 04 23	Remove replace tpl.
;	2014 03 11	Allow to start in an specific line by regular expresion
;	2014 08 25	Switch opener for different paths.
;	2015 02 16	Remove merge from link.
;				More debug options.
;	2015 03 18	Change to codebrowser.
;				Get regepx from file.

#Persistent
#SingleInstance force
SetTitleMatchMode 2

; Query to load the script
sQuery =
Loop
{
	sValue := %A_Index%
	If sValue =
	{
		break
	}
	sQuery = %sQuery% %sValue%
}

;Testing purposes.
If !A_IsCompiled
{
    sQuery = codebrowser:/vagrant/shared/app/public/root/index.php:11
}
; Ini file paremeters
sIniSection := A_ComputerName
sSpace := " "

; Load the data.
sIniFile := RegExReplace(A_ScriptFullPath, "\.\w+$", ".ini" )
IfNotExist, %sIniFile%
{
	MsgBox 64, Tool Open File, Ini file not found`nSince this is the first run`, you must select the editor to use for opening 'codebrowser' links.
	FileSelectFile, sEditorPath, 1
	IfNotExist, %sEditorPath%
	{
		MsgBox, 48, Tool Open file, No editor found.
		ExitApp
	}
	IniWrite, %sEditorPath%, %sIniFile%, %sIniSection%, Editor
	RegWrite, REG_SZ, HKEY_CLASSES_ROOT, codebrowser, URL Protocol
	RegWrite, REG_SZ, HKEY_CLASSES_ROOT, codebrowser\DefaultIcon, , `"%A_ScriptFullPath%`",0
	RegWrite, REG_SZ, HKEY_CLASSES_ROOT, codebrowser\shell\open\command, , `"%A_ScriptFullPath%`" `%1
	RegWrite, REG_SZ, HKEY_CLASSES_ROOT, codebrowser\shell\open\ddeexec, , `%1
	RegWrite, REG_SZ, HKEY_CLASSES_ROOT, codebrowser\shell\open\ddeexec\Application, , codebrowser
	RegWrite, REG_SZ, HKEY_CLASSES_ROOT, codebrowser\shell\open\ddeexec\ifexec,, `%1
	RegWrite, REG_SZ, HKEY_CLASSES_ROOT, codebrowser\shell\open\ddeexec\Topic,, OpenLink

	; Escape slashed in A_ScriptFullPath
	sScriptPath = A_ScriptFullPath
	StringReplace, sScriptPath, A_ScriptFullPath, \, \\, 1

	sRegFile =
	sRegFile = %sRegFile%Windows Registry Editor Version 5.00`n`n
	sRegFile = %sRegFile%[HKEY_CLASSES_ROOT\codebrowser]`n
	sRegFile = %sRegFile%"URL Protocol"=""`n`n
	sRegFile = %sRegFile%[HKEY_CLASSES_ROOT\codebrowser\DefaultIcon]`n
	sRegFile = %sRegFile%@="\"%sScriptPath%\",0"`n`n
	sRegFile = %sRegFile%[HKEY_CLASSES_ROOT\codebrowser\shell]`n
	sRegFile = %sRegFile%[HKEY_CLASSES_ROOT\codebrowser\shell\open]`n
	sRegFile = %sRegFile%[HKEY_CLASSES_ROOT\codebrowser\shell\open\command]`n
	sRegFile = %sRegFile%@="\"%sScriptPath%\" `%1"`n`n

	sRegFile = %sRegFile%[HKEY_CLASSES_ROOT\codebrowser\shell\open\ddeexec]`n
	sRegFile = %sRegFile%@="`%1"`n`n

	sRegFile = %sRegFile%[HKEY_CLASSES_ROOT\codebrowser\shell\open\ddeexec\Application]`n
	sRegFile = %sRegFile%@="codebrowser"`n`n

	sRegFile = %sRegFile%[HKEY_CLASSES_ROOT\codebrowser\shell\open\ddeexec\ifexec]`n
	sRegFile = %sRegFile%@="`%1"`n`n

	sRegFile = %sRegFile%[HKEY_CLASSES_ROOT\codebrowser\shell\open\ddeexec\Topic]`n

	sRegFile = %sRegFile%@="OpenLink"

	FileDelete, codebrowser.reg
	FileAppend, %sRegFile%, codebrowser.reg
	MsgBox, 64, Tool Open file, Success!
}
IniRead, sEditorPath, %sIniFile%, %sIniSection%, Editor

; End loading data

; First match, normal.
If !RegExMatch(sQuery, "^codebrowser:((\w*)\|)?(.*?)(\..{2,4})?(->(.*+)|:(\d+))?$", sMatch)
{
	; Second match direct file path.
	If !RegExMatch(sQuery, "^((\w*)\|)?(.*?)(\..{2,4})?(->(.*+)|:(\d+))?$", sMatch)
	{
		TrayTip, Open file, The sQuery '%sQuery%' does no match expected pattern
		SetTimer, FinishApp, 2000
		Return
	}
}
sFile = %sMatch3%%sMatch4%

; Replace unix style separators to windows.
StringReplace, sFile, sFile, /, \, All
StringReplace, sFile, sFile, `%7C, |, All
StringReplace, sFile, sFile, `%20, %sSpace%, All

;-----------------------------------------------
; Replace known paths for mounted devices and so
;-----------------------------------------------
nPatternsTried = 0
Loop, Read, %A_ScriptDir%\codebrowser_rules.txt
{
    If (RegExMatch(A_LoopReadLine, "^(.*?)(\((\w+)\))?:\s+(.*?)\s*=>\s*(.*?)$", sData)) {
        nPatternsTried++

        ;MsgBox 1: %sData1% - 2: %sData2% - 3: %sData3% - 4: %sData4% - 5: %sData5%
        sLabel := sData1
        sEditor := sData3
        If sEditor = 
        {
            sEditor = Default
        }
        sRuleFind := sData4
        sRuleReplace := sData5

        sNewFile := RegExReplace(sFile, sRuleFind, sRuleReplace)
        If sNewFile != %sFile%
       {
            IfNotExist, %sNewFile%
            {
                IfExist, %A_ScriptDir%\%sNewFile%
                {
                    sFile = %A_ScriptDir%\%sNewFile%
                    break
                }
            }
            Else
            {
                sFile := sNewFile
                break
            }
        }
    }
}

;--------------------------------------------------------
; Verifies the file exists in the root or the script path
;--------------------------------------------------------
IfNotExist, %sFile%
{
    MsgBox, 33, , #3 Not found`n%sFile%`n%sQuery%`n%nPatternsTried% patterns tried. Edit config file?
    IfMsgBox, Cancel
    {
        TrayTip, Open file, #2 Not found file '%sFile%' in %A_ScriptDir%
        SetTimer, FinishApp, 5000
        Return
    }

    StringReplace, clipboard, sFile, \, \\, 1
    sFile = %A_ScriptDir%\codebrowser_rules.txt
}

sExtension := sMatch4
If sMatch7 <>
{
    nLine := sMatch7
}
If sMatch6 <>
{
    nLine = 0
    Loop, read, %sFile%
    {
        nLine++
        If RegExMatch(A_LoopReadLine, sMatch6 )
        {
            Break
        }
    }
}


; Get editor from ini file.
IniRead, sEditorPath, %sIniFile%, %sIniSection%, %sEditor%

If sExpresion = ERROR
{
    ExitApp
}

A_ProgramFiles64 := RegExReplace(A_ProgramFiles, "\s+\(x\d+\)", "" )

StringReplace, sEditorPath, sEditorPath, {MyDocuments}, %A_MyDocuments%
StringReplace, sEditorPath, sEditorPath, {ProgramFiles}, %A_ProgramFiles%


; Check if it is a directory.
result := FileExist(sFile)
If result = D
{
    WinActivate Total Commander
    Send ^g
    Sleep 100
    ;Send ^v{Enter}
    Send %sFile%{Enter}
    SetTimer, FinishApp, 2000
    Return
}

command = %sEditorPath% "%sFile%"
If nLine
{
    IfInString, sEditorPath, notepad++
    {
        command = %command% -n%nLine%
    }
    Else IfInString, sEditorPath, netbeans.exe
    {
        command = %command%:%nLine%
    }
    Else IfInString, sEditorPath, Geany.exe
    {
        command = %command% +%nLine%
    }
    Else IfInString, sEditorPath, phpStorm.exe
    {
        command = %sEditorPath% --line %nLine% "%sFile%"
    }
    Else IfInString, sEditorPath, sublime_text.exe
    {
        command = %sEditorPath% "%sFile%:%nLine%"
    }
}
Run, %command%
TrayTip, Used %sLabel%, File '%sFile%' loaded`n%nPatternsTried% patterns check. %sLabel% used for %sEditor%:`n%sEditorPath%
SetTimer, FinishApp, 2000
Return


FinishApp:
	ExitApp