"use client"

import ChatDetails from "@components/ChatDetails"
import ChatList from "@components/ChatList"
import { useAuth } from '@/contexts/AuthContext';
import { useParams } from "next/navigation"
import { useEffect } from "react"


const ChatPage = () => {
  const { chatId } = useParams()

  const { user: currentUser } = useAuth();

  const seenMessages = async () => {
    try {
      await fetch (`/api/chats/${chatId}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          currentUserId: currentUser.id
        })
      })
    } catch (err) {
      console.log(err)
    }
  }

  useEffect(() => {
    if (currentUser && chatId) seenMessages()
  }, [currentUser, chatId])

  return (
    <div className="main-container">
      <div className="w-1/3 max-lg:hidden"><ChatList currentChatId={chatId}/></div>
      <div className="w-2/3 max-lg:w-full"><ChatDetails chatId={chatId}/></div>
    </div>
  )
}

export default ChatPage