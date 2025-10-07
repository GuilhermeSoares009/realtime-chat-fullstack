import { format } from "date-fns";
import { useRouter } from "next/navigation";

const ChatBox = ({ chat, currentUser, currentChatId }) => {
  const users = chat?.users ?? chat?.members ?? [];
  const otherMember = users.find((member) => (member.id ?? member._id) !== currentUser.id);

  const lastMessage = chat?.last_message ?? (chat?.messages?.length > 0 && chat?.messages[chat?.messages.length - 1]);

  const seen = (lastMessage?.seenBy || []).find((member) => (member.id ?? member._id) === currentUser.id);

  const router = useRouter();

  return (
    <div
      className={`chat-box ${chat.id === currentChatId ? "bg-blue-2" : ""}`}
      onClick={() => router.push(`/chats/${chat.id}`)}
    >
      <div className="chat-info">
        <img
          src={avatarUrl(otherMember, 64)}
          alt="profile-photo"
          className="profilePhoto"
        />

        <div className="flex flex-col gap-1">
          <p className="text-base-bold">{otherMember?.username || otherMember?.name}</p>

          {!lastMessage && <p className="text-small-bold">Started a chat</p>}

          <p className={`last-message ${seen ? "text-small-medium text-grey-3" : "text-small-bold"}`}>
            {lastMessage?.content ?? lastMessage?.text ?? "No messages yet"}
          </p>
        
        </div>
      </div>

      <div>
          <p className="text-base-light text-grey-3">
          {!lastMessage
            ? format(new Date(chat?.createdAt || chat?.created_at), "p")
            : format(new Date(chat?.lastMessageAt || chat?.last_message?.createdAt || chat?.last_message?.created_at), "p")}
        </p>
      </div>
    </div>
  );
};

export default ChatBox;